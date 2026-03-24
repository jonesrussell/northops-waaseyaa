<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class LeadAttachment extends ContentEntityBase
{
    protected string $entityTypeId = 'lead_attachment';

    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'filename',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     */
    public function __construct(array $values = [])
    {
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getLeadId(): int
    {
        return (int) ($this->get('lead_id') ?? 0);
    }

    public function getFilename(): string
    {
        return (string) ($this->get('filename') ?? '');
    }

    public function getStoragePath(): string
    {
        return (string) ($this->get('storage_path') ?? '');
    }

    public function getContentType(): string
    {
        return (string) ($this->get('content_type') ?? '');
    }

    public function getSize(): int
    {
        return (int) ($this->get('size') ?? 0);
    }

    public function getGeneratedAt(): string
    {
        return (string) ($this->get('generated_at') ?? '');
    }
}
