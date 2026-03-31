<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\RfpImportService;
use PHPUnit\Framework\TestCase;

final class RfpImportServiceTest extends TestCase
{
    public function testImportReturnsErrorWhenNorthcloudUrlEmpty(): void
    {
        // Use reflection to create a minimal instance without real dependencies.
        // RfpImportService only needs the URL to be empty to trigger the error path.
        $ref = new \ReflectionClass(RfpImportService::class);
        $service = $ref->newInstanceWithoutConstructor();

        // Set private properties directly.
        $urlProp = $ref->getProperty('northcloudUrl');
        $urlProp->setValue($service, '');

        $stats = $service->import(1, 7);

        $this->assertSame(1, $stats['errors']);
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, $stats['skipped']);
    }

    public function testMapHitWithFullNestedRfpStructure(): void
    {
        $ref = new \ReflectionClass(RfpImportService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('mapHitToRfpData');

        $hit = [
            'id' => 'doc-123',
            'title' => 'Website Redesign RFP',
            'url' => 'https://canadabuys.canada.ca/en/tender/123',
            'body' => 'Full RFP description text',
            'quality_score' => 85,
            'topics' => ['technology'],
            'rfp' => [
                'source_url' => 'https://canadabuys.canada.ca/en/tender/123/details',
                'organization_name' => 'Treasury Board of Canada',
                'closing_date' => '2026-04-15',
                'contact_name' => 'Jane Smith',
                'contact_email' => 'jane.smith@tbs-sct.gc.ca',
                'budget_max' => 50000,
            ],
        ];

        $result = $method->invoke($service, $hit);

        $this->assertSame('doc-123', $result['external_id']);
        $this->assertSame('Website Redesign RFP', $result['label']);
        $this->assertSame('https://canadabuys.canada.ca/en/tender/123/details', $result['source_url']);
        $this->assertSame('Treasury Board of Canada', $result['company_name']);
        $this->assertSame('2026-04-15', $result['closing_date']);
        $this->assertSame('Jane Smith', $result['contact_name']);
        $this->assertSame('jane.smith@tbs-sct.gc.ca', $result['contact_email']);
        $this->assertSame(50000, $result['value']);
        $this->assertSame('Full RFP description text', $result['description']);
        $this->assertSame(85, $result['qualify_rating']);
    }

    public function testMapHitWithMissingRfpKeyFallsBackToTopLevel(): void
    {
        $ref = new \ReflectionClass(RfpImportService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('mapHitToRfpData');

        $hit = [
            'id' => 'doc-456',
            'title' => 'Cloud Migration',
            'url' => 'https://canadabuys.canada.ca/en/tender/456',
            'raw_text' => 'Raw text fallback',
        ];

        $result = $method->invoke($service, $hit);

        $this->assertSame('https://canadabuys.canada.ca/en/tender/456', $result['source_url']);
        $this->assertSame('', $result['company_name']);
        $this->assertSame('', $result['closing_date']);
        $this->assertSame('', $result['contact_name']);
        $this->assertSame('', $result['contact_email']);
        $this->assertSame('', $result['value']);
        $this->assertSame('Raw text fallback', $result['description']);
    }

    public function testMapHitWithPartialNestedRfpFields(): void
    {
        $ref = new \ReflectionClass(RfpImportService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('mapHitToRfpData');

        $hit = [
            'id' => 'doc-789',
            'title' => 'Network Upgrade',
            'url' => 'https://canadabuys.canada.ca/en/tender/789',
            'rfp' => [
                'organization_name' => 'DND',
                // source_url, closing_date, contact_name, contact_email, budget_max all missing
            ],
        ];

        $result = $method->invoke($service, $hit);

        // Falls back to top-level url when rfp.source_url missing
        $this->assertSame('https://canadabuys.canada.ca/en/tender/789', $result['source_url']);
        $this->assertSame('DND', $result['company_name']);
        $this->assertSame('', $result['closing_date']);
        $this->assertSame('', $result['contact_name']);
        $this->assertSame('', $result['contact_email']);
        $this->assertSame('', $result['value']);
    }
}
