<?php

declare(strict_types=1);

namespace App\Domain\Signal;

final readonly class IngestResult
{
    public function __construct(
        public int $ingested = 0,
        public int $skipped = 0,
        public int $leadsCreated = 0,
        public int $leadsMatched = 0,
        public int $unmatched = 0,
        public array $errors = [],
    ) {}

    public function toArray(): array
    {
        return [
            'ingested' => $this->ingested,
            'skipped' => $this->skipped,
            'leads_created' => $this->leadsCreated,
            'leads_matched' => $this->leadsMatched,
            'unmatched' => $this->unmatched,
        ];
    }
}
