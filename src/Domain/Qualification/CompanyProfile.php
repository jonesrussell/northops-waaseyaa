<?php

declare(strict_types=1);

namespace App\Domain\Qualification;

final readonly class CompanyProfile
{
    public function __construct(
        public string $description,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self($config['pipeline']['company_profile'] ?? 'NorthOps');
    }
}
