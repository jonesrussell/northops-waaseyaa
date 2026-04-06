<?php

declare(strict_types=1);

namespace App\Support;

interface NotifierInterface
{
    public function sendEmbed(array $embed): void;
}
