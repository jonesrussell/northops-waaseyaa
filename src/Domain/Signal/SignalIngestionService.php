<?php

declare(strict_types=1);

namespace App\Domain\Signal;

use App\Domain\Pipeline\LeadFactoryInterface;
use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Entity\LeadSignal;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalIngestionService
{
    private const VALID_SIGNAL_TYPES = [
        'rfp', 'funding_win', 'job_posting', 'tech_migration',
        'outdated_website', 'hn_mention', 'new_program',
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly SignalMatcherInterface $signalMatcher,
        private readonly LeadFactoryInterface $leadFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly int $autoCreateThreshold = 50,
        private readonly int $defaultBrandId = 1,
    ) {}

    public function ingest(array $signals): IngestResult
    {
        $ingested = 0;
        $skipped = 0;
        $leadsCreated = 0;
        $leadsMatched = 0;
        $unmatched = 0;
        $errors = [];

        foreach ($signals as $signalData) {
            $validation = $this->validate($signalData);
            if ($validation !== null) {
                $errors[] = $validation;
                continue;
            }

            if ($this->isDuplicate($signalData)) {
                $skipped++;
                continue;
            }

            $signal = $this->createSignal($signalData);
            $lead = $this->signalMatcher->match($signalData);

            if ($lead !== null) {
                $signal->set('lead_id', $lead->id());
                $this->entityTypeManager->getStorage('lead_signal')->save($signal);
                $leadsMatched++;
            } else {
                $strength = (int) ($signalData['strength'] ?? 50);
                if ($strength >= $this->autoCreateThreshold) {
                    $lead = $this->leadFactory->fromSignal($signalData, $this->defaultBrandId);
                    $signal->set('lead_id', $lead->id());
                    $this->entityTypeManager->getStorage('lead_signal')->save($signal);
                    $leadsCreated++;
                } else {
                    $unmatched++;
                }
            }

            $this->dispatcher->dispatch(new SignalIngestedEvent($signal, $lead));
            $ingested++;
        }

        return new IngestResult($ingested, $skipped, $leadsCreated, $leadsMatched, $unmatched, $errors);
    }

    private function validate(array $data): ?string
    {
        foreach (['signal_type', 'external_id', 'source', 'label'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return "Missing required field: {$field}";
            }
        }

        if (!in_array($data['signal_type'], self::VALID_SIGNAL_TYPES, true)) {
            return "Invalid signal_type: {$data['signal_type']}";
        }

        return null;
    }

    private function isDuplicate(array $data): bool
    {
        $ids = $this->entityTypeManager->getStorage('lead_signal')
            ->getQuery()
            ->condition('external_id', $data['external_id'])
            ->condition('source', $data['source'])
            ->execute();

        return $ids !== [];
    }

    private function createSignal(array $data): LeadSignal
    {
        $signal = new LeadSignal([
            'label' => $data['label'],
            'signal_type' => $data['signal_type'],
            'source' => $data['source'],
            'source_url' => $data['source_url'] ?? '',
            'external_id' => $data['external_id'],
            'strength' => (int) ($data['strength'] ?? 50),
            'payload' => $data['payload'] ?? [],
            'organization_name' => $data['organization_name'] ?? '',
            'sector' => $data['sector'] ?? '',
            'province' => $data['province'] ?? '',
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $this->entityTypeManager->getStorage('lead_signal')->save($signal);

        return $signal;
    }
}
