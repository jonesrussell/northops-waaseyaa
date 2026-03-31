<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Domain\Pipeline\EventSubscriber\LeadQualifiedSubscriber;
use App\Domain\Qualification\QualificationService;
use App\Domain\Qualification\SectorNormalizer;
use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpRequestException;

/**
 * Fetches RFPs from north-cloud's search API and imports them as leads.
 *
 * Shared by both the /api/leads/import endpoint and the pipeline:import-rfps CLI command.
 */
final class RfpImportService
{
    /**
     * IT-relevant keywords used as a full-text query filter when fetching RFPs.
     * Matches are OR'd — an RFP needs to mention at least one term.
     */
    private const RELEVANCE_QUERY = 'software OR website OR web OR IT OR cloud OR network OR cybersecurity OR infrastructure OR telecom OR SaaS OR DevOps OR hosting OR migration OR digital OR application OR database OR server';

    public function __construct(
        private readonly LeadFactory $leadFactory,
        private readonly LeadManager $leadManager,
        private readonly QualificationService $qualificationService,
        private readonly ?LeadQualifiedSubscriber $leadQualifiedSubscriber,
        private readonly HttpClientInterface $httpClient,
        private readonly string $northcloudUrl,
    ) {}

    /**
     * Import RFPs from north-cloud. Returns import statistics.
     *
     * @return array{imported: int, skipped: int, qualified: int, errors: int}
     */
    public function import(int $brandId, int $lookbackDays = 7, bool $dryRun = false, bool $autoQualify = false): array
    {
        $fromDate = (new \DateTimeImmutable("-{$lookbackDays} days"))->format('Y-m-d\TH:i:s\Z');
        $stats = ['imported' => 0, 'skipped' => 0, 'qualified' => 0, 'errors' => 0];
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
                    continue;
                }

                $stats['imported']++;

                if ($autoQualify) {
                    $qualified = $this->qualifyLead($lead);
                    if ($qualified) {
                        $stats['qualified']++;
                    }
                }
            }

            $page++;
        } while ($page <= $totalPages);

        return $stats;
    }

    /**
     * Run AI qualification on a lead and persist the results.
     */
    private function qualifyLead(\App\Entity\Lead $lead): bool
    {
        try {
            $qualification = $this->qualificationService->qualify($lead);
        } catch (\RuntimeException) {
            return false;
        }

        $this->leadManager->update($lead, [
            'qualify_rating' => $qualification['rating'],
            'qualify_confidence' => $qualification['confidence'],
            'qualify_keywords' => json_encode($qualification['keywords'], JSON_THROW_ON_ERROR),
            'qualify_notes' => $qualification['summary'] ?? '',
            'qualify_raw' => $qualification['raw'],
            'sector' => $qualification['sector'] ?? $lead->getSector(),
            'score' => $qualification['score'],
            'recommended_brand' => $qualification['recommended_brand'],
        ]);

        $this->leadQualifiedSubscriber?->handle($lead, $qualification);

        return true;
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

        try {
            $response = $this->httpClient->post(
                $this->northcloudUrl . '/api/v1/search',
                ['Accept' => 'application/json'],
                [
                    'query' => self::RELEVANCE_QUERY,
                    'filters' => [
                        'content_type' => 'rfp',
                        'from_date' => $fromDate,
                        'min_quality_score' => 40,
                    ],
                    'pagination' => [
                        'page' => $page,
                        'size' => 100,
                    ],
                    'sort' => [
                        'field' => 'published_date',
                        'order' => 'desc',
                    ],
                ],
            );
        } catch (HttpRequestException) {
            return null;
        }

        if (!$response->isSuccess()) {
            return null;
        }

        $decoded = json_decode($response->body, true);

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
            'source_url' => $hit['canonical_url'] ?? $hit['source'] ?? $hit['url'] ?? '',
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
