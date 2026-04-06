<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Entity\Lead;

interface QualificationHandlerInterface
{
    /**
     * @param array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string} $qualificationResult
     */
    public function handle(Lead $lead, array $qualificationResult): void;
}
