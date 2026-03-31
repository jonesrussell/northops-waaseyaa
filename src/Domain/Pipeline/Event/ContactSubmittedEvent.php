<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Event;

final class ContactSubmittedEvent
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $message,
    ) {}
}
