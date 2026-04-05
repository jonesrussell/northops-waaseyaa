<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Signal\SignalIngestionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalController
{
    public function __construct(
        private readonly SignalIngestionService $ingestionService,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly string $apiKey,
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Invalid API key.']],
            ], 401);
        }

        try {
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'Invalid JSON.']],
            ], 400);
        }

        $signals = $body['signals'] ?? [];
        if (!is_array($signals) || $signals === []) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'signals array is required and must not be empty.']],
            ], 400);
        }

        $result = $this->ingestionService->ingest($signals);

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => $result->toArray(),
        ], 201);
    }

    public function listUnmatched(Request $request): JsonResponse
    {
        $storage = $this->entityTypeManager->getStorage('lead_signal');
        $ids = $storage->getQuery()
            ->condition('lead_id', null)
            ->execute();

        $signals = [];
        foreach ($ids as $id) {
            $signal = $storage->load((int) $id);
            if ($signal === null) {
                continue;
            }
            $signals[] = [
                'id' => $signal->id(),
                'label' => $signal->getLabel(),
                'signal_type' => $signal->getSignalType(),
                'source' => $signal->getSource(),
                'strength' => $signal->getStrength(),
                'organization_name' => $signal->getOrganizationName(),
                'created_at' => $signal->getCreatedAt(),
            ];
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => $signals,
        ]);
    }

    private function validateApiKey(Request $request): bool
    {
        if ($this->apiKey === '') {
            return false;
        }
        $provided = $request->headers->get('X-Api-Key', '');
        return hash_equals($this->apiKey, $provided);
    }
}
