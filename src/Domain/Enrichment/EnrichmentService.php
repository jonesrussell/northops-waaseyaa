<?php

declare(strict_types=1);

namespace App\Domain\Enrichment;

use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\HttpClient\HttpClientInterface;

final class EnrichmentService
{
    private const DEFAULT_TYPES = ['company_intel', 'tech_stack', 'hiring'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly HttpClientInterface $httpClient,
        private readonly string $northcloudUrl,
        private readonly string $callbackApiKey,
        private readonly string $appUrl,
    ) {}

    public function requestEnrichment(Lead $lead, array $types = []): void
    {
        if ($types === []) {
            $types = self::DEFAULT_TYPES;
        }

        $signals = $this->loadSignals($lead);

        $payload = [
            'lead_id' => $lead->id(),
            'company_name' => $lead->getCompanyName(),
            'domain' => '',
            'sector' => $lead->getSector(),
            'requested_types' => $types,
            'signals' => $signals,
            'callback_url' => rtrim($this->appUrl, '/') . '/api/leads/' . $lead->id() . '/enrichment',
            'callback_api_key' => $this->callbackApiKey,
        ];

        $this->httpClient->post(
            "{$this->northcloudUrl}/api/v1/enrich",
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function loadSignals(Lead $lead): array
    {
        $ids = $this->entityTypeManager->getStorage('lead_signal')
            ->getQuery()
            ->condition('lead_id', $lead->id())
            ->execute();

        $signals = [];
        $storage = $this->entityTypeManager->getStorage('lead_signal');

        foreach ($ids as $id) {
            $signal = $storage->load((int) $id);
            if ($signal !== null) {
                $signals[] = [
                    'signal_type' => $signal->getSignalType(),
                    'label' => $signal->getLabel(),
                    'strength' => $signal->getStrength(),
                ];
            }
        }

        return $signals;
    }
}
