<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\RoutingService;
use PHPUnit\Framework\TestCase;

final class RoutingServiceTest extends TestCase
{
    private RoutingService $service;

    protected function setUp(): void
    {
        $this->service = new RoutingService();
    }

    // Rule 1: northops.ca contact form
    public function testNorthopsContactFormRoutes100(): void
    {
        $result = $this->service->route(['source' => 'inbound', 'lead_source' => 'northops_contact']);

        $this->assertSame('northops', $result['brand']);
        $this->assertSame(100, $result['confidence']);
        $this->assertSame('northops_contact_form', $result['rule']);
    }

    // Rule 2: webnetworks contact form
    public function testWebnetContactFormRoutes100(): void
    {
        $result = $this->service->route(['source' => 'inbound', 'lead_source' => 'webnet_contact']);

        $this->assertSame('webnet', $result['brand']);
        $this->assertSame(100, $result['confidence']);
        $this->assertSame('webnet_contact_form', $result['rule']);
    }

    // Rule 3: brand explicitly set
    public function testExplicitBrandRoutes100(): void
    {
        $result = $this->service->route(['brand_id' => 5, 'source' => 'rfp']);

        $this->assertSame('explicit', $result['brand']);
        $this->assertSame(100, $result['confidence']);
        $this->assertSame('explicit_brand', $result['rule']);
    }

    // Rule 4: non-profit org
    public function testNonProfitRoutesToWebnet95(): void
    {
        $result = $this->service->route(['organization_type' => 'non_profit']);

        $this->assertSame('webnet', $result['brand']);
        $this->assertSame(95, $result['confidence']);
        $this->assertSame('non_profit_org', $result['rule']);
    }

    // Rule 4 variant: charity
    public function testCharityRoutesToWebnet95(): void
    {
        $result = $this->service->route(['organization_type' => 'charity']);

        $this->assertSame('webnet', $result['brand']);
        $this->assertSame(95, $result['confidence']);
    }

    // Rule 5: Indigenous org
    public function testIndigenousOrgRoutesToWebnet95(): void
    {
        $result = $this->service->route(['organization_type' => 'indigenous']);

        $this->assertSame('webnet', $result['brand']);
        $this->assertSame(95, $result['confidence']);
        $this->assertSame('indigenous_org', $result['rule']);
    }

    // Rule 6: SaaS sector
    public function testSaasSectorRoutesToNorthops90(): void
    {
        $result = $this->service->route(['sector' => 'SaaS']);

        $this->assertSame('northops', $result['brand']);
        $this->assertSame(90, $result['confidence']);
        $this->assertSame('tech_sector', $result['rule']);
    }

    // Rule 6: AI/ML sector
    public function testAiMlSectorRoutesToNorthops90(): void
    {
        $result = $this->service->route(['sector' => 'AI/ML']);

        $this->assertSame('northops', $result['brand']);
        $this->assertSame(90, $result['confidence']);
    }

    // Rule 6: DevOps sector
    public function testDevOpsSectorRoutesToNorthops90(): void
    {
        $result = $this->service->route(['sector' => 'DevOps']);

        $this->assertSame('northops', $result['brand']);
        $this->assertSame(90, $result['confidence']);
    }

    // Rule 7: funding lead source
    public function testFundingSourceRoutesToWebnet90(): void
    {
        $result = $this->service->route(['lead_source' => 'funding']);

        $this->assertSame('webnet', $result['brand']);
        $this->assertSame(90, $result['confidence']);
        $this->assertSame('funding_source', $result['rule']);
    }

    // Rule 8: website_audit lead source
    public function testWebsiteAuditRoutesToWebnet90(): void
    {
        $result = $this->service->route(['lead_source' => 'website_audit']);

        $this->assertSame('webnet', $result['brand']);
        $this->assertSame(90, $result['confidence']);
        $this->assertSame('audit_source', $result['rule']);
    }

    // Rule 9: signal (founder intent)
    public function testSignalSourceRoutesToNorthops85(): void
    {
        $result = $this->service->route(['lead_source' => 'signal']);

        $this->assertSame('northops', $result['brand']);
        $this->assertSame(85, $result['confidence']);
        $this->assertSame('signal_source', $result['rule']);
    }

    // Rule 10: budget >15K with technical scope
    public function testHighBudgetTechnicalRoutesToNorthops80(): void
    {
        $result = $this->service->route([
            'value' => '20000',
            'sector' => 'IT',
        ]);

        $this->assertSame('northops', $result['brand']);
        $this->assertSame(80, $result['confidence']);
        $this->assertSame('high_budget_technical', $result['rule']);
    }

    // Rule 10: budget >15K without technical scope does NOT match
    public function testHighBudgetNonTechnicalDoesNotMatchRule10(): void
    {
        $result = $this->service->route([
            'value' => '20000',
            'sector' => 'Other',
        ]);

        $this->assertNotSame('high_budget_technical', $result['rule']);
    }

    // Rule 11: government digital services
    public function testGovernmentDigitalRoutesToBoth50(): void
    {
        $result = $this->service->route(['sector' => 'Government']);

        $this->assertSame('both', $result['brand']);
        $this->assertSame(50, $result['confidence']);
        $this->assertSame('government_digital', $result['rule']);
    }

    // Rule 12: no match → manual review
    public function testNoMatchRoutesToManual(): void
    {
        $result = $this->service->route([]);

        $this->assertSame('manual', $result['brand']);
        $this->assertSame(0, $result['confidence']);
        $this->assertSame('no_match', $result['rule']);
    }

    // Precedence: higher confidence wins
    public function testHigherConfidenceRuleWins(): void
    {
        // Non-profit (95%) beats funding source (90%)
        $result = $this->service->route([
            'organization_type' => 'non_profit',
            'lead_source' => 'funding',
        ]);

        $this->assertSame(95, $result['confidence']);
        $this->assertSame('non_profit_org', $result['rule']);
    }

    // Precedence: explicit brand beats everything
    public function testExplicitBrandBeatsOrgType(): void
    {
        $result = $this->service->route([
            'brand_id' => 3,
            'organization_type' => 'non_profit',
        ]);

        $this->assertSame(100, $result['confidence']);
        $this->assertSame('explicit_brand', $result['rule']);
    }

    // Result shape
    public function testResultIncludesAllKeys(): void
    {
        $result = $this->service->route(['sector' => 'SaaS']);

        $this->assertArrayHasKey('brand', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('rule', $result);
    }
}
