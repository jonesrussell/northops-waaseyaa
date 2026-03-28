<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Outreach extends ContentEntityBase
{
    protected string $entityTypeId = 'outreach';

    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'subject',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['channel'])) {
            $values['channel'] = 'email';
        }
        if (!isset($values['status'])) {
            $values['status'] = 'draft';
        }

        parent::__construct($values + [
            'type' => 'outreach',
            'lead_uuid' => '',
            'brand_uuid' => '',
            'subject' => '',
            'body_summary' => '',
            'sent_at' => null,
            'replied_at' => null,
            'template_used' => null,
        ], $this->entityTypeId, $this->entityKeys);
    }

    // Channel & Status

    public function getChannel(): string
    {
        return (string) ($this->get('channel') ?? 'email');
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?? 'draft');
    }

    // Relations

    public function getLeadUuid(): string
    {
        return (string) ($this->get('lead_uuid') ?? '');
    }

    public function getBrandUuid(): string
    {
        return (string) ($this->get('brand_uuid') ?? '');
    }

    // Content

    public function getSubject(): string
    {
        return (string) ($this->get('subject') ?? '');
    }

    public function getBodySummary(): string
    {
        return (string) ($this->get('body_summary') ?? '');
    }

    // Template

    public function getTemplateUsed(): ?string
    {
        $value = $this->get('template_used');
        return $value !== null ? (string) $value : null;
    }

    // Lifecycle

    public function getSentAt(): ?string
    {
        $value = $this->get('sent_at');
        return $value !== null ? (string) $value : null;
    }

    public function getRepliedAt(): ?string
    {
        $value = $this->get('replied_at');
        return $value !== null ? (string) $value : null;
    }
}
