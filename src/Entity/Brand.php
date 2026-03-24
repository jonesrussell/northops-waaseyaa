<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Brand extends ContentEntityBase
{
    protected string $entityTypeId = 'brand';

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

    public function getSlug(): string
    {
        return (string) ($this->get('slug') ?? '');
    }

    public function getLogoPath(): string
    {
        return (string) ($this->get('logo_path') ?? '');
    }

    public function getPrimaryColor(): string
    {
        return (string) ($this->get('primary_color') ?? '');
    }

    public function getTagline(): string
    {
        return (string) ($this->get('tagline') ?? '');
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
