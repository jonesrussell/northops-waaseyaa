<?php

declare(strict_types=1);

namespace App\Surface\Action;

use App\Domain\Pipeline\StageTransitionRules;
use Waaseyaa\AdminSurface\Action\SurfaceActionHandler;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadTransitionStageAction implements SurfaceActionHandler
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function handle(string $type, array $payload): AdminSurfaceResultData
    {
        $id = $payload['id'] ?? null;
        $targetStage = $payload['stage'] ?? null;

        if ($id === null || $id === '') {
            return AdminSurfaceResultData::error(400, 'Missing field', 'Payload must include an id field.');
        }

        if ($targetStage === null || $targetStage === '') {
            return AdminSurfaceResultData::error(400, 'Missing field', 'Payload must include a stage field.');
        }

        $targetStage = (string) $targetStage;

        if (!StageTransitionRules::isValidStage($targetStage)) {
            return AdminSurfaceResultData::error(422, 'Invalid stage', "Stage '{$targetStage}' is not a valid pipeline stage.");
        }

        $storage = $this->entityTypeManager->getStorage('lead');
        $lead = $storage->load((string) $id);

        if ($lead === null) {
            return AdminSurfaceResultData::error(404, 'Not found', "Lead '{$id}' does not exist.");
        }

        $currentStage = (string) $lead->get('stage');

        if (!StageTransitionRules::canTransition($currentStage, $targetStage)) {
            return AdminSurfaceResultData::error(
                422,
                'Invalid transition',
                "Cannot transition from '{$currentStage}' to '{$targetStage}'.",
            );
        }

        $errors = StageTransitionRules::validateTransition($currentStage, $targetStage, $lead->toArray());

        if ($errors !== []) {
            return AdminSurfaceResultData::error(422, 'Validation failed', implode(' ', $errors));
        }

        $now = date('c');
        $lead->set('stage', $targetStage);
        $lead->set('stage_changed_at', $now);
        $lead->set('updated_at', $now);

        $storage->save($lead);

        return AdminSurfaceResultData::success([
            'type' => 'lead',
            'id' => (string) $lead->id(),
            'attributes' => $lead->toArray(),
        ]);
    }
}
