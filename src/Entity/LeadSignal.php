<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class LeadSignal extends ContentEntityBase
{
    protected string $entityTypeId = 'lead_signal';

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
        if (!isset($values['strength'])) {
            $values['strength'] = 50;
        }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getLabel(): string
    {
        return (string) ($this->get('label') ?? '');
    }

    public function getLeadId(): ?int
    {
        $val = $this->get('lead_id');
        return $val !== null ? (int) $val : null;
    }

    public function getSignalType(): string
    {
        return (string) ($this->get('signal_type') ?? '');
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

    public function getStrength(): int
    {
        return (int) ($this->get('strength') ?? 50);
    }

    public function getPayload(): array
    {
        $val = $this->get('payload');
        if (is_string($val)) {
            return json_decode($val, true) ?: [];
        }
        return is_array($val) ? $val : [];
    }

    public function getOrganizationName(): string
    {
        return (string) ($this->get('organization_name') ?? '');
    }

    public function getSector(): string
    {
        return (string) ($this->get('sector') ?? '');
    }

    public function getProvince(): string
    {
        return (string) ($this->get('province') ?? '');
    }

    public function getExpiresAt(): ?string
    {
        $val = $this->get('expires_at');
        return $val !== null && $val !== '' ? (string) $val : null;
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }
}
