<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\LeadEnrichment;
use PHPUnit\Framework\TestCase;

final class LeadEnrichmentTest extends TestCase
{
    public function testFieldsStoredAndRetrieved(): void
    {
        $enrichment = new LeadEnrichment([
            'label' => 'Company intel for Health Canada',
            'lead_id' => 42,
            'provider' => 'north-cloud',
            'enrichment_type' => 'company_intel',
            'data' => ['website' => 'https://example.gc.ca', 'tech_stack' => ['WordPress']],
            'confidence' => 0.85,
        ]);

        $this->assertSame('Company intel for Health Canada', $enrichment->getLabel());
        $this->assertSame(42, $enrichment->getLeadId());
        $this->assertSame('north-cloud', $enrichment->getProvider());
        $this->assertSame('company_intel', $enrichment->getEnrichmentType());
        $this->assertSame(['website' => 'https://example.gc.ca', 'tech_stack' => ['WordPress']], $enrichment->getData());
        $this->assertSame(0.85, $enrichment->getConfidence());
        $this->assertNotEmpty($enrichment->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $enrichment = new LeadEnrichment([
            'label' => 'Minimal enrichment',
            'lead_id' => 1,
            'provider' => 'manual',
            'enrichment_type' => 'tech_stack',
            'data' => [],
        ]);

        $this->assertSame(0.0, $enrichment->getConfidence());
        $this->assertSame([], $enrichment->getData());
        $this->assertNotEmpty($enrichment->getCreatedAt());
    }
}
