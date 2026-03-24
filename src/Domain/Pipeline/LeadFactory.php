<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Domain\Qualification\SectorNormalizer;
use App\Entity\ContactSubmission;
use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadFactory
{
    public function __construct(
        private readonly LeadManager $leadManager,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    /**
     * Create a lead from a contact form submission.
     */
    public function fromContactSubmission(ContactSubmission $submission, int $brandId): Lead
    {
        return $this->leadManager->create([
            'label' => mb_substr($submission->getMessage(), 0, 255),
            'brand_id' => $brandId,
            'source' => 'inbound',
            'stage' => 'lead',
            'contact_name' => $submission->getName(),
            'contact_email' => $submission->getEmail(),
        ]);
    }

    /**
     * Create a lead from an RFP import. Returns null if duplicate external_id exists.
     *
     * @param array<string, mixed> $rfpData
     */
    public function fromRfpImport(array $rfpData, int $brandId): ?Lead
    {
        $externalId = $rfpData['external_id'] ?? '';

        if ($externalId !== '') {
            $storage = $this->entityTypeManager->getStorage('lead');
            $existing = $storage->loadByProperties(['external_id' => $externalId]);

            if ($existing !== []) {
                return null;
            }
        }

        $sector = SectorNormalizer::normalize($rfpData['sector'] ?? null);

        $data = [
            'label' => $rfpData['label'] ?? $rfpData['title'] ?? 'RFP Import',
            'brand_id' => $brandId,
            'source' => 'rfp',
            'stage' => $rfpData['stage'] ?? 'lead',
            'external_id' => $externalId,
            'sector' => $sector,
            'contact_name' => $rfpData['contact_name'] ?? '',
            'contact_email' => $rfpData['contact_email'] ?? '',
            'company_name' => $rfpData['company_name'] ?? '',
            'source_url' => $rfpData['source_url'] ?? '',
            'value' => $rfpData['value'] ?? '',
            'closing_date' => $rfpData['closing_date'] ?? '',
            'draft_pdf_markdown' => $rfpData['description'] ?? '',
        ];

        // Pre-populate qualification rating from north-cloud quality score.
        if (isset($rfpData['qualify_rating']) && is_numeric($rfpData['qualify_rating'])) {
            $data['qualify_rating'] = (int) $rfpData['qualify_rating'];
        }

        return $this->leadManager->create($data);
    }

    /**
     * Create a lead from manual entry with full validation.
     *
     * @param array<string, mixed> $data
     */
    public function fromManualEntry(array $data): Lead
    {
        return $this->leadManager->create($data);
    }
}
