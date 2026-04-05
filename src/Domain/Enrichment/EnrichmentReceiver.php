<?php

declare(strict_types=1);

namespace App\Domain\Enrichment;

use App\Domain\Enrichment\Event\LeadEnrichedEvent;
use App\Entity\Lead;
use App\Entity\LeadEnrichment;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class EnrichmentReceiver
{
    private const VALID_TYPES = [
        'company_intel', 'contact_discovery', 'tech_stack', 'financial', 'competitor_analysis',
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function receive(Lead $lead, array $enrichmentData): LeadEnrichment
    {
        $this->validate($enrichmentData);

        $enrichment = new LeadEnrichment([
            'label' => sprintf('%s enrichment for %s', $enrichmentData['enrichment_type'], $lead->getLabel()),
            'lead_id' => $lead->id(),
            'provider' => $enrichmentData['provider'],
            'enrichment_type' => $enrichmentData['enrichment_type'],
            'data' => $enrichmentData['data'],
            'confidence' => (float) $enrichmentData['confidence'],
        ]);

        $this->entityTypeManager->getStorage('lead_enrichment')->save($enrichment);
        $this->dispatcher->dispatch(new LeadEnrichedEvent($lead, $enrichment));

        return $enrichment;
    }

    private function validate(array $data): void
    {
        if (!isset($data['provider']) || trim((string) $data['provider']) === '') {
            throw new \InvalidArgumentException('Missing required field: provider');
        }

        $type = $data['enrichment_type'] ?? '';
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid enrichment_type: {$type}");
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new \InvalidArgumentException('Missing required field: data (must be array)');
        }

        if (!isset($data['confidence'])) {
            throw new \InvalidArgumentException('Missing required field: confidence');
        }
    }
}
