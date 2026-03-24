<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Domain\Pipeline\EventSubscriber\LeadCreatedSubscriber;
use App\Domain\Pipeline\EventSubscriber\StageChangedSubscriber;
use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadManager
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ?LeadCreatedSubscriber $leadCreatedSubscriber = null,
        private readonly ?StageChangedSubscriber $stageChangedSubscriber = null,
    ) {}

    /**
     * Create a new lead with validation.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Lead
    {
        $stage = $data['stage'] ?? 'lead';
        if (!StageTransitionRules::isValidStage($stage)) {
            throw new \InvalidArgumentException("Invalid stage: '{$stage}'.");
        }

        $source = $data['source'] ?? '';
        if ($source !== '' && !StageTransitionRules::isValidSource($source)) {
            throw new \InvalidArgumentException("Invalid source: '{$source}'.");
        }

        $lead = new Lead($data);
        $this->entityTypeManager->getStorage('lead')->save($lead);

        $this->leadCreatedSubscriber?->handle($lead);

        return $lead;
    }

    /**
     * Update mutable fields on an existing lead.
     *
     * @param array<string, mixed> $data
     */
    public function update(Lead $lead, array $data): Lead
    {
        $mutableFields = [
            'label', 'contact_name', 'contact_email', 'contact_phone',
            'company_name', 'value', 'finder_fee_percent', 'closing_date',
            'assigned_to', 'sector', 'source_url', 'external_id',
            'qualify_rating', 'qualify_confidence', 'qualify_keywords',
            'qualify_notes', 'qualify_raw',
            'draft_email_subject', 'draft_email_body', 'draft_pdf_markdown',
        ];

        foreach ($data as $field => $fieldValue) {
            if (in_array($field, $mutableFields, true)) {
                $lead->set($field, $fieldValue);
            }
        }

        $lead->set('updated_at', date('c'));
        $this->entityTypeManager->getStorage('lead')->save($lead);

        return $lead;
    }

    /**
     * Transition a lead to a new stage with validation.
     */
    public function changeStage(Lead $lead, string $newStage): Lead
    {
        $currentStage = $lead->getStage();

        $leadData = [
            'contact_email' => $lead->getContactEmail(),
            'value' => $lead->getValue(),
        ];

        $errors = StageTransitionRules::validateTransition($currentStage, $newStage, $leadData);

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $lead->set('stage', $newStage);
        $lead->set('stage_changed_at', date('c'));
        $lead->set('updated_at', date('c'));
        $this->entityTypeManager->getStorage('lead')->save($lead);

        $this->stageChangedSubscriber?->handle($lead, $currentStage, $newStage);

        return $lead;
    }

    /**
     * Soft-delete a lead by setting deleted_at.
     */
    public function softDelete(Lead $lead): void
    {
        $lead->set('deleted_at', date('c'));
        $this->entityTypeManager->getStorage('lead')->save($lead);
    }
}
