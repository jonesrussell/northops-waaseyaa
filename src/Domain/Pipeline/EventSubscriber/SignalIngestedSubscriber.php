<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Entity\LeadActivity;
use App\Support\DiscordNotifier;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalIngestedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DiscordNotifier $notifier,
    ) {}

    public function __invoke(SignalIngestedEvent $event): void
    {
        $signal = $event->signal;
        $lead = $event->lead;

        $this->notifier->sendEmbed([
            'title' => sprintf('[Signal] %s: %s', $signal->getSignalType(), $signal->getLabel()),
            'color' => $lead !== null ? 0x2ECC71 : 0xF39C12,
            'fields' => array_values(array_filter([
                ['name' => 'Strength', 'value' => (string) $signal->getStrength(), 'inline' => true],
                ['name' => 'Source', 'value' => $signal->getSource(), 'inline' => true],
                $signal->getOrganizationName() !== '' ? ['name' => 'Organization', 'value' => $signal->getOrganizationName(), 'inline' => true] : null,
                ['name' => 'Lead', 'value' => $lead !== null ? $lead->getLabel() : 'Unmatched', 'inline' => true],
            ])),
            'timestamp' => date('c'),
        ]);

        if ($lead !== null) {
            $activity = new LeadActivity([
                'lead_id' => $lead->id(),
                'user_id' => 'system',
                'action' => 'signal_ingested',
                'payload' => json_encode([
                    'signal_type' => $signal->getSignalType(),
                    'signal_id' => $signal->id(),
                    'strength' => $signal->getStrength(),
                ], JSON_THROW_ON_ERROR),
            ]);
            $this->entityTypeManager->getStorage('lead_activity')->save($activity);
        }
    }
}
