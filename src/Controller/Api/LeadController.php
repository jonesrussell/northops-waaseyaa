<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Pipeline\StageTransitionRules;
use App\Domain\Qualification\QualificationService;
use App\Domain\Qualification\SectorNormalizer;
use App\Entity\Lead;
use App\Entity\LeadActivity;
use App\Entity\LeadAttachment;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadController
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly LeadManager $leadManager,
        private readonly LeadFactory $leadFactory,
        private readonly QualificationService $qualificationService,
        private readonly array $config,
    ) {}

    // ---------------------------------------------------------------
    // Leads CRUD
    // ---------------------------------------------------------------

    /**
     * GET /api/leads
     */
    public function listLeads(Request $request): JsonResponse
    {
        $storage = $this->entityTypeManager->getStorage('lead');
        $query = $storage->getQuery()
            ->accessCheck(false)
            ->notExists('deleted_at')
            ->sort('created_at', 'DESC');

        // Filter: stage
        $stage = $request->query->get('stage');
        if ($stage !== null && $stage !== '') {
            $query->condition('stage', $stage);
        }

        // Filter: brand_id
        $brandId = $request->query->get('brand_id');
        if ($brandId !== null && $brandId !== '') {
            $query->condition('brand_id', (int) $brandId);
        }

        // Filter: sector
        $sector = $request->query->get('sector');
        if ($sector !== null && $sector !== '') {
            $query->condition('sector', $sector);
        }

        // Filter: source
        $source = $request->query->get('source');
        if ($source !== null && $source !== '') {
            $query->condition('source', $source);
        }

        // Filter: assigned_to
        $assignedTo = $request->query->get('assigned_to');
        if ($assignedTo !== null && $assignedTo !== '') {
            $query->condition('assigned_to', $assignedTo);
        }

        // Filter: search (CONTAINS on label, contact_name, contact_email, company_name)
        $search = $request->query->get('search');
        if ($search !== null && $search !== '') {
            // EntityQuery doesn't support OR conditions natively.
            // We apply CONTAINS on label as primary search; for a richer
            // search we'd need a custom query. This is a pragmatic first pass.
            $query->condition('label', $search, 'CONTAINS');
        }

        $ids = $query->execute();

        if ($ids === []) {
            return new JsonResponse([]);
        }

        $entities = $storage->loadMultiple($ids);
        $result = [];
        foreach ($entities as $entity) {
            if ($entity instanceof Lead) {
                $result[] = $this->leadToArray($entity);
            }
        }

        return new JsonResponse($result);
    }

    /**
     * POST /api/leads
     */
    public function createLead(Request $request): JsonResponse
    {
        $data = $this->parseJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $lead = $this->leadFactory->fromManualEntry($data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($this->leadToArray($lead), 201);
    }

    /**
     * GET /api/leads/{id}
     */
    public function getLead(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        return new JsonResponse($this->leadToArray($lead));
    }

    /**
     * PATCH /api/leads/{id}
     */
    public function updateLead(Request $request, string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        $data = $this->parseJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $lead = $this->leadManager->update($lead, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($this->leadToArray($lead));
    }

    /**
     * DELETE /api/leads/{id}
     */
    public function deleteLead(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        $this->leadManager->softDelete($lead);

        return new JsonResponse(null, 204);
    }

    /**
     * PATCH /api/leads/{id}/stage
     */
    public function changeStage(Request $request, string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        $data = $this->parseJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $newStage = $data['stage'] ?? '';
        if ($newStage === '') {
            return new JsonResponse(['error' => 'Field "stage" is required.'], 422);
        }

        try {
            $lead = $this->leadManager->changeStage($lead, $newStage);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($this->leadToArray($lead));
    }

    // ---------------------------------------------------------------
    // Qualification
    // ---------------------------------------------------------------

    /**
     * POST /api/leads/{id}/qualify
     */
    public function qualifyLead(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        try {
            $qualification = $this->qualificationService->qualify($lead);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        $this->leadManager->update($lead, [
            'qualify_rating' => $qualification['rating'],
            'qualify_confidence' => $qualification['confidence'],
            'qualify_keywords' => implode(', ', $qualification['keywords']),
            'qualify_notes' => $qualification['summary'] ?? '',
            'qualify_raw' => $qualification['raw'],
            'sector' => $qualification['sector'] ?? $lead->getSector(),
        ]);

        return new JsonResponse($qualification);
    }

    // ---------------------------------------------------------------
    // Activity
    // ---------------------------------------------------------------

    /**
     * GET /api/leads/{id}/activity
     */
    public function listActivity(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        $storage = $this->entityTypeManager->getStorage('lead_activity');
        $activityIds = $storage->getQuery()
            ->accessCheck(false)
            ->condition('lead_id', (int) $id)
            ->sort('created_at', 'DESC')
            ->execute();

        if ($activityIds === []) {
            return new JsonResponse([]);
        }

        $entities = $storage->loadMultiple($activityIds);
        $result = [];
        foreach ($entities as $entity) {
            if ($entity instanceof LeadActivity) {
                $result[] = $this->activityToArray($entity);
            }
        }

        return new JsonResponse($result);
    }

    /**
     * POST /api/leads/{id}/activity
     */
    public function createActivity(Request $request, string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        $data = $this->parseJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $action = $data['action'] ?? '';
        if ($action === '') {
            return new JsonResponse(['error' => 'Field "action" is required.'], 422);
        }

        $payload = $data['payload'] ?? [];

        $activity = new LeadActivity([
            'lead_id' => (int) $id,
            'action' => $action,
            'payload' => is_array($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : (string) $payload,
            'user_id' => $data['user_id'] ?? '',
        ]);

        $this->entityTypeManager->getStorage('lead_activity')->save($activity);

        return new JsonResponse($this->activityToArray($activity), 201);
    }

    // ---------------------------------------------------------------
    // Attachments
    // ---------------------------------------------------------------

    /**
     * GET /api/leads/{id}/attachments
     */
    public function listAttachments(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return new JsonResponse(['error' => 'Lead not found.'], 404);
        }

        $storage = $this->entityTypeManager->getStorage('lead_attachment');
        $attachmentIds = $storage->getQuery()
            ->accessCheck(false)
            ->condition('lead_id', (int) $id)
            ->sort('created_at', 'DESC')
            ->execute();

        if ($attachmentIds === []) {
            return new JsonResponse([]);
        }

        $entities = $storage->loadMultiple($attachmentIds);
        $result = [];
        foreach ($entities as $entity) {
            if ($entity instanceof LeadAttachment) {
                $result[] = [
                    'id' => $entity->id(),
                    'lead_id' => $entity->getLeadId(),
                    'filename' => $entity->getFilename(),
                    'storage_path' => $entity->getStoragePath(),
                    'content_type' => $entity->getContentType(),
                    'size' => $entity->getSize(),
                    'generated_at' => $entity->getGeneratedAt(),
                ];
            }
        }

        return new JsonResponse($result);
    }

    // ---------------------------------------------------------------
    // Import
    // ---------------------------------------------------------------

    /**
     * POST /api/leads/import
     */
    public function importLeads(Request $request): JsonResponse
    {
        // API key auth.
        $expectedKey = $this->config['pipeline']['api_key'] ?? '';
        $providedKey = $request->headers->get('X-Api-Key', '');

        if ($expectedKey === '' || $providedKey !== $expectedKey) {
            return new JsonResponse(['error' => 'Unauthorized.'], 401);
        }

        $northcloudUrl = $this->config['pipeline']['northcloud_url'] ?? '';
        if ($northcloudUrl === '') {
            return new JsonResponse(['error' => 'North-cloud URL not configured.'], 500);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($northcloudUrl . '/api/search/rfp', false, $context);

        if ($response === false) {
            return new JsonResponse(['error' => 'Failed to connect to north-cloud.'], 502);
        }

        $rfpResults = json_decode($response, true);
        if (!is_array($rfpResults)) {
            return new JsonResponse(['error' => 'Invalid response from north-cloud.'], 502);
        }

        // Determine default brand ID.
        $defaultBrandSlug = $this->config['pipeline']['default_brand'] ?? 'northops';
        $brandId = $this->resolveDefaultBrandId($defaultBrandSlug);

        $imported = 0;
        $skipped = 0;

        foreach ($rfpResults as $rfp) {
            if (!is_array($rfp)) {
                $skipped++;
                continue;
            }

            $lead = $this->leadFactory->fromRfpImport($rfp, $brandId);
            if ($lead === null) {
                $skipped++;
            } else {
                $imported++;
            }
        }

        return new JsonResponse(['imported' => $imported, 'skipped' => $skipped]);
    }

    // ---------------------------------------------------------------
    // Brands & Config
    // ---------------------------------------------------------------

    /**
     * GET /api/brands
     */
    public function listBrands(): JsonResponse
    {
        $storage = $this->entityTypeManager->getStorage('brand');
        $brandIds = $storage->getQuery()
            ->accessCheck(false)
            ->sort('name', 'ASC')
            ->execute();

        if ($brandIds === []) {
            return new JsonResponse([]);
        }

        $entities = $storage->loadMultiple($brandIds);
        $result = [];
        foreach ($entities as $entity) {
            if ($entity instanceof \App\Entity\Brand) {
                $result[] = [
                    'id' => $entity->id(),
                    'name' => $entity->getName(),
                    'slug' => $entity->getSlug(),
                    'logo_path' => $entity->getLogoPath(),
                    'primary_color' => $entity->getPrimaryColor(),
                    'tagline' => $entity->getTagline(),
                ];
            }
        }

        return new JsonResponse($result);
    }

    /**
     * GET /api/config
     */
    public function getConfig(): JsonResponse
    {
        return new JsonResponse([
            'stages' => StageTransitionRules::STAGES,
            'sectors' => SectorNormalizer::SECTORS,
            'sources' => StageTransitionRules::SOURCES,
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function loadLead(string $id): ?Lead
    {
        $storage = $this->entityTypeManager->getStorage('lead');
        $entity = $storage->load((int) $id);

        if (!$entity instanceof Lead) {
            return null;
        }

        // Exclude soft-deleted leads.
        if ($entity->getDeletedAt() !== '') {
            return null;
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    private function leadToArray(Lead $lead): array
    {
        return [
            'id' => $lead->id(),
            'label' => $lead->getLabel(),
            'brand_id' => $lead->getBrandId(),
            'source' => $lead->getSource(),
            'source_url' => $lead->getSourceUrl(),
            'external_id' => $lead->getExternalId(),
            'stage' => $lead->getStage(),
            'stage_changed_at' => $lead->getStageChangedAt(),
            'contact_name' => $lead->getContactName(),
            'contact_email' => $lead->getContactEmail(),
            'contact_phone' => $lead->getContactPhone(),
            'company_name' => $lead->getCompanyName(),
            'value' => $lead->getValue(),
            'finder_fee_percent' => $lead->getFinderFeePercent(),
            'closing_date' => $lead->getClosingDate(),
            'assigned_to' => $lead->getAssignedTo(),
            'sector' => $lead->getSector(),
            'qualify_rating' => $lead->getQualifyRating(),
            'qualify_confidence' => $lead->getQualifyConfidence(),
            'qualify_keywords' => $lead->getQualifyKeywords(),
            'qualify_notes' => $lead->getQualifyNotes(),
            'draft_email_subject' => $lead->getDraftEmailSubject(),
            'draft_email_body' => $lead->getDraftEmailBody(),
            'draft_pdf_markdown' => $lead->getDraftPdfMarkdown(),
            'created_at' => $lead->getCreatedAt(),
            'updated_at' => $lead->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityToArray(LeadActivity $activity): array
    {
        return [
            'id' => $activity->id(),
            'lead_id' => $activity->getLeadId(),
            'user_id' => $activity->getUserId(),
            'action' => $activity->getAction(),
            'payload' => $activity->getPayload(),
            'created_at' => $activity->getCreatedAt(),
        ];
    }

    /**
     * Parse JSON body from request. Returns decoded array or a 400 JsonResponse on failure.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function parseJson(Request $request): array|JsonResponse
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return new JsonResponse(['error' => 'Request body is empty.'], 400);
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new JsonResponse(['error' => 'Invalid JSON: ' . $e->getMessage()], 400);
        }

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Request body must be a JSON object.'], 400);
        }

        return $data;
    }

    /**
     * Resolve brand ID from slug, falling back to 1.
     */
    private function resolveDefaultBrandId(string $slug): int
    {
        $storage = $this->entityTypeManager->getStorage('brand');
        $ids = $storage->getQuery()
            ->accessCheck(false)
            ->condition('slug', $slug)
            ->execute();

        if ($ids !== []) {
            return (int) $ids[0];
        }

        return 1;
    }
}
