<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Pipeline\Event\ContactSubmittedEvent;
use App\Support\DiscordNotifier;

final class ContactSubmittedSubscriber
{
    public function __construct(
        private readonly DiscordNotifier $discordNotifier,
    ) {}

    public function __invoke(ContactSubmittedEvent $event): void
    {
        $this->discordNotifier->notifyContactSubmission(
            $event->name,
            $event->email,
            $event->message,
        );
    }
}
