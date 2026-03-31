<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Lead extends ContentEntityBase
{
    protected string $entityTypeId = 'lead';

    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'label',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['stage'])) {
            $values['stage'] = 'lead';
        }
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    // Identity

    public function getLabel(): string
    {
        return (string) ($this->get('label') ?? '');
    }

    // Brand & source

    public function getBrandId(): int
    {
        return (int) ($this->get('brand_id') ?? 0);
    }

    public function getSource(): string
    {
        return (string) ($this->get('source') ?? '');
    }

    public function getSourceUrl(): string
    {
        return (string) ($this->get('source_url') ?? '');
    }

    public function getExternalId(): string
    {
        return (string) ($this->get('external_id') ?? '');
    }

    // Pipeline

    public function getStage(): string
    {
        return (string) ($this->get('stage') ?? 'lead');
    }

    public function getStageChangedAt(): string
    {
        return (string) ($this->get('stage_changed_at') ?? '');
    }

    // Contact

    public function getContactName(): string
    {
        return (string) ($this->get('contact_name') ?? '');
    }

    public function getContactEmail(): string
    {
        return (string) ($this->get('contact_email') ?? '');
    }

    public function getContactPhone(): string
    {
        return (string) ($this->get('contact_phone') ?? '');
    }

    public function getCompanyName(): string
    {
        return (string) ($this->get('company_name') ?? '');
    }

    // Deal

    public function getValue(): string
    {
        return (string) ($this->get('value') ?? '0');
    }

    public function getFinderFeePercent(): string
    {
        return (string) ($this->get('finder_fee_percent') ?? '0');
    }

    public function getClosingDate(): string
    {
        return (string) ($this->get('closing_date') ?? '');
    }

    // Qualification

    public function getQualifyRating(): int
    {
        return (int) ($this->get('qualify_rating') ?? 0);
    }

    public function getQualifyConfidence(): float
    {
        return (float) ($this->get('qualify_confidence') ?? 0.0);
    }

    public function getQualifyKeywords(): string
    {
        return (string) ($this->get('qualify_keywords') ?? '');
    }

    public function getQualifyNotes(): string
    {
        return (string) ($this->get('qualify_notes') ?? '');
    }

    public function getQualifyRaw(): string
    {
        return (string) ($this->get('qualify_raw') ?? '');
    }

    public function getSector(): string
    {
        return (string) ($this->get('sector') ?? '');
    }

    // Scoring & routing

    public function getBudgetRange(): string
    {
        return (string) ($this->get('budget_range') ?? '');
    }

    public function getUrgency(): string
    {
        return (string) ($this->get('urgency') ?? '');
    }

    public function getTier(): string
    {
        return (string) ($this->get('tier') ?? '');
    }

    public function getOrganizationType(): string
    {
        return (string) ($this->get('organization_type') ?? '');
    }

    public function getFundingStatus(): string
    {
        return (string) ($this->get('funding_status') ?? '');
    }

    public function getRoutingConfidence(): int
    {
        return (int) ($this->get('routing_confidence') ?? 0);
    }

    public function getLeadSource(): string
    {
        return (string) ($this->get('lead_source') ?? '');
    }

    public function getLastScoredAt(): string
    {
        return (string) ($this->get('last_scored_at') ?? '');
    }

    public function getSpecialistContext(): string
    {
        return (string) ($this->get('specialist_context') ?? '');
    }

    // Drafts

    public function getDraftEmailSubject(): string
    {
        return (string) ($this->get('draft_email_subject') ?? '');
    }

    public function getDraftEmailBody(): string
    {
        return (string) ($this->get('draft_email_body') ?? '');
    }

    public function getDraftPdfMarkdown(): string
    {
        return (string) ($this->get('draft_pdf_markdown') ?? '');
    }

    // Lifecycle

    public function getAssignedTo(): string
    {
        return (string) ($this->get('assigned_to') ?? '');
    }

    public function getDeletedAt(): string
    {
        return (string) ($this->get('deleted_at') ?? '');
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }

    public function getUpdatedAt(): string
    {
        return (string) ($this->get('updated_at') ?? '');
    }
}
