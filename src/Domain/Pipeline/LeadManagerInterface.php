<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Entity\Lead;

interface LeadManagerInterface
{
    public function update(Lead $lead, array $data): Lead;
}
