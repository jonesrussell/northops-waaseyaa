<?php

declare(strict_types=1);

namespace App\Surface\Action;

use App\Domain\Qualification\QualificationService;
use Waaseyaa\AdminSurface\Action\SurfaceActionHandler;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadQualifyAction implements SurfaceActionHandler
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly QualificationService $qualificationService,
    ) {}

    public function handle(string $type, array $payload): AdminSurfaceResultData
    {
        $id = $payload['id'] ?? null;

        if ($id === null || $id === '') {
            return AdminSurfaceResultData::error(400, 'Missing field', 'Payload must include an id field.');
        }

        $storage = $this->entityTypeManager->getStorage('lead');
        $lead = $storage->load((string) $id);

        if ($lead === null) {
            return AdminSurfaceResultData::error(404, 'Not found', "Lead '{$id}' does not exist.");
        }

        /** @var \App\Entity\Lead $lead */
        try {
            $result = $this->qualificationService->qualify($lead);
        } catch (\RuntimeException $e) {
            return AdminSurfaceResultData::error(502, 'Qualification failed', $e->getMessage());
        }

        // Apply qualification results to the lead
        $lead->set('qualify_rating', $result['rating']);
        $lead->set('qualify_confidence', $result['confidence']);
        $lead->set('qualify_keywords', implode(', ', $result['keywords']));
        $lead->set('qualify_notes', $result['summary'] ?? '');
        $lead->set('qualify_raw', $result['raw']);

        if (isset($result['sector']) && $result['sector'] !== null) {
            $lead->set('sector', $result['sector']);
        }

        $lead->set('updated_at', date('c'));
        $storage->save($lead);

        // Reload to get persisted state
        $lead = $storage->load((string) $id);

        return AdminSurfaceResultData::success([
            'type' => 'lead',
            'id' => (string) $lead->id(),
            'attributes' => $lead->toArray(),
        ]);
    }
}
