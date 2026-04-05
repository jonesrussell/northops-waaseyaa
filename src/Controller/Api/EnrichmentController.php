<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Enrichment\EnrichmentReceiver;
use App\Domain\Enrichment\EnrichmentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;

final class EnrichmentController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EnrichmentService $enrichmentService,
        private readonly EnrichmentReceiver $enrichmentReceiver,
        private readonly string $apiKey,
    ) {}

    public function requestEnrichment(Request $request, string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        $body = json_decode((string) $request->getContent(), true) ?? [];
        $types = $body['types'] ?? [];

        try {
            $this->enrichmentService->requestEnrichment($lead, $types);
        } catch (\Throwable $e) {
            error_log(sprintf('[NorthOps] Enrichment request failed: %s', $e->getMessage()));
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '502', 'title' => 'Bad Gateway', 'detail' => 'Enrichment service unavailable.']],
            ], 502);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => [
                'enrichments_requested' => count($types ?: ['company_intel', 'tech_stack', 'hiring']),
                'types' => $types ?: ['company_intel', 'tech_stack', 'hiring'],
            ],
        ]);
    }

    public function receiveEnrichment(Request $request, string $id): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Invalid API key.']],
            ], 401);
        }

        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        try {
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'Invalid JSON.']],
            ], 400);
        }

        try {
            $enrichment = $this->enrichmentReceiver->receive($lead, $body);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => [
                'id' => $enrichment->id(),
                'enrichment_type' => $enrichment->getEnrichmentType(),
                'provider' => $enrichment->getProvider(),
            ],
        ], 201);
    }

    public function listSignals(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        $ids = $this->entityTypeManager->getStorage('lead_signal')
            ->getQuery()
            ->condition('lead_id', (int) $id)
            ->execute();

        $signals = [];
        $storage = $this->entityTypeManager->getStorage('lead_signal');
        foreach ($ids as $signalId) {
            $signal = $storage->load((int) $signalId);
            if ($signal === null) {
                continue;
            }
            $signals[] = [
                'id' => $signal->id(),
                'signal_type' => $signal->getSignalType(),
                'source' => $signal->getSource(),
                'label' => $signal->getLabel(),
                'strength' => $signal->getStrength(),
                'organization_name' => $signal->getOrganizationName(),
                'source_url' => $signal->getSourceUrl(),
                'expires_at' => $signal->getExpiresAt(),
                'created_at' => $signal->getCreatedAt(),
                'payload' => $signal->getPayload(),
            ];
        }

        return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => $signals]);
    }

    public function listEnrichments(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        $ids = $this->entityTypeManager->getStorage('lead_enrichment')
            ->getQuery()
            ->condition('lead_id', (int) $id)
            ->execute();

        $enrichments = [];
        $storage = $this->entityTypeManager->getStorage('lead_enrichment');
        foreach ($ids as $eid) {
            $e = $storage->load((int) $eid);
            if ($e === null) {
                continue;
            }
            $enrichments[] = [
                'id' => $e->id(),
                'provider' => $e->getProvider(),
                'enrichment_type' => $e->getEnrichmentType(),
                'confidence' => $e->getConfidence(),
                'data' => $e->getData(),
                'created_at' => $e->getCreatedAt(),
            ];
        }

        return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => $enrichments]);
    }

    private function loadLead(string $id): ?\App\Entity\Lead
    {
        return $this->entityTypeManager->getStorage('lead')->load((int) $id);
    }

    private function notFound(string $id): JsonResponse
    {
        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => "Lead {$id} not found."]],
        ], 404);
    }

    private function validateApiKey(Request $request): bool
    {
        if ($this->apiKey === '') {
            return false;
        }
        return hash_equals($this->apiKey, $request->headers->get('X-Api-Key', ''));
    }
}
