<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Entity\Lead;

interface LeadFactoryInterface
{
    public function fromSignal(array $signalData, int $brandId): Lead;
}
