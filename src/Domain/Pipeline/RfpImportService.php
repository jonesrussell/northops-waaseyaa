<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Domain\Qualification\SectorNormalizer;

/**
 * Fetches RFPs from north-cloud's search API and imports them as leads.
 *
 * Shared by both the /api/leads/import endpoint and the pipeline:import-rfps CLI command.
 */
final class RfpImportService
{
    public function __construct(
        private readonly LeadFactory $leadFactory,
        private readonly string $northcloudUrl,
    ) {}

    /**
     * Import RFPs from north-cloud. Returns import statistics.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function import(int $brandId, int $lookbackDays = 7, bool $dryRun = false): array
    {
        $fromDate = (new \DateTimeImmutable("-{$lookbackDays} days"))->format('Y-m-d\TH:i:s\Z');
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $page = 1;

        do {
            $response = $this->fetchPage($fromDate, $page);

            if ($response === null) {
                $stats['errors']++;
                break;
            }

            $hits = $response['hits'] ?? [];
            $totalPages = $response['total_pages'] ?? 1;

            foreach ($hits as $hit) {
                if (!is_array($hit)) {
                    $stats['errors']++;
                    continue;
                }

                $rfpData = $this->mapHitToRfpData($hit);

                if ($dryRun) {
                    $stats['imported']++;
                    continue;
                }

                $lead = $this->leadFactory->fromRfpImport($rfpData, $brandId);
                if ($lead === null) {
                    $stats['skipped']++;
                } else {
                    $stats['imported']++;
                }
            }

            $page++;
        } while ($page <= $totalPages);

        return $stats;
    }

    /**
     * Fetch a single page of RFP results from north-cloud.
     *
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $fromDate, int $page): ?array
    {
        if ($this->northcloudUrl === '') {
            return null;
        }

        $requestBody = json_encode([
            'query' => '',
            'filters' => [
                'content_type' => 'rfp',
                'from_date' => $fromDate,
            ],
            'pagination' => [
                'page' => $page,
                'size' => 100,
            ],
            'sort' => [
                'field' => 'published_date',
                'order' => 'desc',
            ],
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $requestBody,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->northcloudUrl . '/api/v1/search', false, $context);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Map a north-cloud search hit to the array format expected by LeadFactory::fromRfpImport().
     *
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    private function mapHitToRfpData(array $hit): array
    {
        $categories = $hit['topics'] ?? [];
        $sector = null;
        if (is_array($categories) && $categories !== []) {
            $sector = SectorNormalizer::normalize($categories[0]);
        }

        return [
            'external_id' => $hit['id'] ?? '',
            'label' => $hit['title'] ?? 'RFP Import',
            'source_url' => $hit['canonical_url'] ?? '',
            'company_name' => $hit['organization_name'] ?? '',
            'closing_date' => $hit['closing_date'] ?? '',
            'description' => $hit['body'] ?? $hit['raw_text'] ?? '',
            'sector' => $sector,
            'qualify_rating' => $hit['quality_score'] ?? null,
            'contact_name' => $hit['contact_name'] ?? '',
            'contact_email' => $hit['contact_email'] ?? '',
            'value' => $hit['budget_max'] ?? '',
        ];
    }
}
