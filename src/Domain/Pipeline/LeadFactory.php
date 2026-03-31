<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

use App\Domain\Qualification\SectorNormalizer;
use App\Entity\ContactSubmission;
use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadFactory
{
    /**
     * Keywords that indicate an RFP is relevant to IT/web services.
     * At least one must appear in title or description for import.
     */
    private const IT_RELEVANCE_KEYWORDS = [
        'software', 'website', 'web ', 'web-', ' it ', 'cloud', 'network',
        'cybersecurity', 'cyber', 'infrastructure', 'telecom', 'saas',
        'devops', 'hosting', 'migration', 'digital', 'application',
        'database', 'server', 'computing', 'data centre', 'data center',
        'information technology', 'managed services', 'helpdesk', 'help desk',
        'firewall', 'erp', 'crm', 'api', 'platform', 'automation',
    ];

    /** @var array<string, int> Cached brand slug → ID mappings */
    private array $brandCache = [];

    public function __construct(
        private readonly LeadManager $leadManager,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly RoutingService $routingService,
    ) {}

    /**
     * Create a lead from a contact form submission.
     */
    public function fromContactSubmission(ContactSubmission $submission, int $brandId): Lead
    {
        $data = [
            'label' => mb_substr($submission->getMessage(), 0, 255),
            'brand_id' => $brandId,
            'source' => 'inbound',
            'stage' => 'lead',
            'contact_name' => $submission->getName(),
            'contact_email' => $submission->getEmail(),
        ];

        return $this->leadManager->create($this->applyRouting($data));
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
            $existing = $this->entityTypeManager->getStorage('lead')->getQuery()
                ->accessCheck(false)
                ->condition('external_id', $externalId)
                ->execute();

            if ($existing !== []) {
                return null;
            }
        }

        if (!$this->isItRelevant($rfpData)) {
            return null;
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

        return $this->leadManager->create($this->applyRouting($data));
    }

    /**
     * Create a lead from manual entry with full validation.
     *
     * @param array<string, mixed> $data
     */
    public function fromManualEntry(array $data): Lead
    {
        return $this->leadManager->create($this->applyRouting($data));
    }

    /**
     * Run routing rules and enrich lead data with brand assignment and confidence.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyRouting(array $data): array
    {
        $result = $this->routingService->route($data);

        $data['routing_confidence'] = $result['confidence'];

        // Only override brand_id if routing resolved to a specific brand
        if (\in_array($result['brand'], ['northops', 'webnet'], true)) {
            $resolvedId = $this->resolveBrandId($result['brand']);
            if ($resolvedId > 0) {
                $data['brand_id'] = $resolvedId;
            }
        }

        return $data;
    }

    private function resolveBrandId(string $slug): int
    {
        if (isset($this->brandCache[$slug])) {
            return $this->brandCache[$slug];
        }

        try {
            $ids = $this->entityTypeManager->getStorage('brand')->getQuery()
                ->accessCheck(false)
                ->condition('slug', $slug)
                ->execute();

            $id = $ids !== [] ? (int) reset($ids) : 0;
        } catch (\Throwable) {
            $id = 0;
        }

        $this->brandCache[$slug] = $id;

        return $id;
    }

    /**
     * Check if an RFP is relevant to IT/web services based on title and description.
     *
     * @param array<string, mixed> $rfpData
     */
    private function isItRelevant(array $rfpData): bool
    {
        $text = strtolower(
            ($rfpData['label'] ?? '') . ' ' . ($rfpData['title'] ?? '') . ' ' . ($rfpData['description'] ?? '')
        );

        foreach (self::IT_RELEVANCE_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
