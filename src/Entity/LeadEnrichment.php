<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class LeadEnrichment extends ContentEntityBase
{
    protected string $entityTypeId = 'lead_enrichment';

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
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }
        if (!isset($values['confidence'])) {
            $values['confidence'] = 0.0;
        }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getLabel(): string
    {
        return (string) ($this->get('label') ?? '');
    }

    public function getLeadId(): int
    {
        return (int) ($this->get('lead_id') ?? 0);
    }

    public function getProvider(): string
    {
        return (string) ($this->get('provider') ?? '');
    }

    public function getEnrichmentType(): string
    {
        return (string) ($this->get('enrichment_type') ?? '');
    }

    public function getData(): array
    {
        $val = $this->get('data');
        if (is_string($val)) {
            return json_decode($val, true) ?: [];
        }
        return is_array($val) ? $val : [];
    }

    public function getConfidence(): float
    {
        return (float) ($this->get('confidence') ?? 0.0);
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }
}
