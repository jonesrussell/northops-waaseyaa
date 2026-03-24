<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class LeadActivity extends ContentEntityBase
{
    protected string $entityTypeId = 'lead_activity';

    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'action',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getLeadId(): int
    {
        return (int) ($this->get('lead_id') ?? 0);
    }

    public function getUserId(): string
    {
        return (string) ($this->get('user_id') ?? '');
    }

    public function getAction(): string
    {
        return (string) ($this->get('action') ?? '');
    }

    public function getPayload(): string
    {
        return (string) ($this->get('payload') ?? '');
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }
}
