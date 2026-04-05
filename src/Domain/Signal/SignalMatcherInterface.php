<?php

declare(strict_types=1);

namespace App\Domain\Signal;

use App\Entity\Lead;

interface SignalMatcherInterface
{
    public function match(array $signalData): ?Lead;
}
