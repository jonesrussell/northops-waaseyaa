<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Pipeline\LeadManagerInterface;
use App\Domain\Qualification\QualifierInterface;
use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Entity\Lead;
use App\Entity\LeadActivity;
use App\Support\NotifierInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalIngestedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly NotifierInterface $notifier,
        private readonly ?QualifierInterface $qualificationService = null,
        private readonly ?QualificationHandlerInterface $qualifiedSubscriber = null,
        private readonly ?LeadManagerInterface $leadManager = null,
        private readonly bool $autoQualify = false,
    ) {}

    public function __invoke(SignalIngestedEvent $event): void
    {
        $signal = $event->signal;
        $lead = $event->lead;

        $this->notifyDiscord($signal, $lead);

        if ($lead !== null) {
            $this->recordActivity($lead, $signal);
            $this->maybeAutoQualify($lead);
        }
    }

    private function notifyDiscord(mixed $signal, ?Lead $lead): void
    {
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
    }

    private function recordActivity(Lead $lead, mixed $signal): void
    {
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

    private function maybeAutoQualify(Lead $lead): void
    {
        if (!$this->autoQualify || $this->qualificationService === null || $this->qualifiedSubscriber === null || $this->leadManager === null) {
            return;
        }

        try {
            $result = $this->qualificationService->qualify($lead);

            $updateData = [
                'qualify_rating' => $result['rating'],
                'qualify_confidence' => $result['confidence'],
                'qualify_keywords' => json_encode($result['keywords'], JSON_THROW_ON_ERROR),
                'qualify_notes' => $result['summary'] ?? '',
                'qualify_raw' => $result['raw'],
                'sector' => $result['sector'] ?? $lead->getSector(),
                'score' => $result['score'],
                'recommended_brand' => $result['recommended_brand'],
            ];

            $brandId = $this->resolveBrandId($result['recommended_brand']);
            if ($brandId !== null) {
                $updateData['brand_id'] = $brandId;
            }

            $this->leadManager->update($lead, $updateData);

            $this->qualifiedSubscriber->handle($lead, $result);
        } catch (\Throwable $e) {
            $this->notifier->sendEmbed([
                'title' => '[Signal] Auto-qualification failed',
                'color' => 0xE74C3C,
                'fields' => [
                    ['name' => 'Lead', 'value' => $lead->getLabel(), 'inline' => true],
                    ['name' => 'Error', 'value' => mb_substr($e->getMessage(), 0, 200), 'inline' => false],
                ],
                'timestamp' => date('c'),
            ]);
        }
    }

    private function resolveBrandId(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        // Alias scoring service slugs to seeded brand slugs.
        $aliases = ['webnet' => 'web-networks'];
        $slug = $aliases[$slug] ?? $slug;

        $ids = $this->entityTypeManager->getStorage('brand')
            ->getQuery()
            ->condition('slug', $slug)
            ->execute();

        return $ids !== [] ? (int) reset($ids) : null;
    }
}
