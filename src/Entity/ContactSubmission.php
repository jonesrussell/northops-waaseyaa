<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class ContactSubmission extends ContentEntityBase
{
    protected string $entityTypeId = 'contact_submission';

    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'name',
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

    public function getName(): string
    {
        return (string) ($this->get('name') ?? '');
    }

    public function getEmail(): string
    {
        return (string) ($this->get('email') ?? '');
    }

    public function getMessage(): string
    {
        return (string) ($this->get('message') ?? '');
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }
}
