<?php

declare(strict_types=1);

namespace App\Domain\Signal\Event;

use App\Entity\Lead;
use App\Entity\LeadSignal;

final readonly class SignalIngestedEvent
{
    public function __construct(
        public LeadSignal $signal,
        public ?Lead $lead = null,
    ) {}
}
