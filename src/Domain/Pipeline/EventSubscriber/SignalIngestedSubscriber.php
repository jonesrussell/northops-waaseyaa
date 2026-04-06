<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

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
        if (!$this->autoQualify || $this->qualificationService === null || $this->qualifiedSubscriber === null) {
            return;
        }

        try {
            $result = $this->qualificationService->qualify($lead);

            $lead->set('qualification_rating', $result['rating']);
            $lead->set('qualification_score', $result['score']);
            $lead->set('sector', $result['sector'] ?? '');
            $this->entityTypeManager->getStorage('lead')->save($lead);

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
}
