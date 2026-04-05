<?php

declare(strict_types=1);

namespace App\Domain\Enrichment\Event;

use App\Entity\Lead;
use App\Entity\LeadEnrichment;

final readonly class LeadEnrichedEvent
{
    public function __construct(
        public Lead $lead,
        public LeadEnrichment $enrichment,
    ) {}
}
