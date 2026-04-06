<?php

declare(strict_types=1);

namespace App\Domain\Qualification;

use App\Entity\Lead;

interface QualifierInterface
{
    /**
     * @return array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string, score: int, recommended_brand: string}
     */
    public function qualify(Lead $lead): array;
}
