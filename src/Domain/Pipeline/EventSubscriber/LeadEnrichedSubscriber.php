<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Enrichment\Event\LeadEnrichedEvent;
use App\Entity\LeadActivity;
use App\Support\DiscordNotifier;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadEnrichedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DiscordNotifier $notifier,
    ) {}

    public function __invoke(LeadEnrichedEvent $event): void
    {
        $lead = $event->lead;
        $enrichment = $event->enrichment;

        $this->notifier->sendEmbed([
            'title' => sprintf('[Enrichment] %s for %s', $enrichment->getEnrichmentType(), $lead->getLabel()),
            'color' => 0x3498DB,
            'fields' => [
                ['name' => 'Provider', 'value' => $enrichment->getProvider(), 'inline' => true],
                ['name' => 'Confidence', 'value' => sprintf('%.0f%%', $enrichment->getConfidence() * 100), 'inline' => true],
                ['name' => 'Type', 'value' => $enrichment->getEnrichmentType(), 'inline' => true],
            ],
            'timestamp' => date('c'),
        ]);

        $activity = new LeadActivity([
            'lead_id' => $lead->id(),
            'user_id' => 'system',
            'action' => 'lead_enriched',
            'payload' => json_encode([
                'enrichment_type' => $enrichment->getEnrichmentType(),
                'provider' => $enrichment->getProvider(),
                'confidence' => $enrichment->getConfidence(),
            ], JSON_THROW_ON_ERROR),
        ]);
        $this->entityTypeManager->getStorage('lead_activity')->save($activity);
    }
}
