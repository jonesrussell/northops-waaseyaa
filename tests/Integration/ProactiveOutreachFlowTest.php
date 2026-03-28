<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Pipeline\OutreachTemplateRenderer;
use App\Domain\Pipeline\ProspectScoringService;
use PHPUnit\Framework\TestCase;

final class ProactiveOutreachFlowTest extends TestCase
{
    private ProspectScoringService $scorer;
    private OutreachTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->scorer = new ProspectScoringService();
        $this->renderer = new OutreachTemplateRenderer();
    }

    public function testWebNetFlow_NonProfitOutdatedWebsite(): void
    {
        // Score the lead
        $scored = $this->scorer->score([
            'title'        => 'Website Modernization for MiningWatch Canada',
            'description'  => 'Drupal 7 migration',
            'qualify_rating' => 7,
            'value'        => 60_000.0,
            'closing_date' => (new \DateTimeImmutable('+21 days'))->format('Y-m-d'),
            'sector'       => 'IT',
            'signal_type'  => 'outdated_website',
            'org_type'     => 'non_profit',
        ]);

        $this->assertGreaterThanOrEqual(50, $scored['score']);
        $this->assertSame('webnet', $scored['recommended_brand']);

        // Render the outreach template
        $outreach = $this->renderer->render('outdated_website', 'webnet', [
            'organization_name' => 'MiningWatch Canada',
            'contact_name'      => 'Jamie Kneen',
            'signal_detail'     => 'Drupal 7 migration',
            'sender_name'       => 'Web Networks Team',
        ]);

        $this->assertStringContainsString('MiningWatch Canada', $outreach['subject']);
        $this->assertStringContainsString('Web Networks', $outreach['body']);
        $this->assertSame('webnet', $outreach['brand']);
    }

    public function testNorthOpsFlow_CommercialClient(): void
    {
        // Score the lead
        $scored = $this->scorer->score([
            'title'          => 'Custom API Platform for TechStartup Inc',
            'description'    => 'REST API + dashboard',
            'qualify_rating' => 8,
            'value'          => 80_000.0,
            'closing_date'   => (new \DateTimeImmutable('+30 days'))->format('Y-m-d'),
            'sector'         => 'Software',
            'signal_type'    => 'new_program',
            'org_type'       => 'commercial',
        ]);

        $this->assertSame('northops', $scored['recommended_brand']);

        // Render the outreach template
        $outreach = $this->renderer->render('new_program', 'northops', [
            'organization_name' => 'TechStartup Inc',
            'contact_name'      => 'CTO',
            'signal_detail'     => 'REST API + dashboard',
            'sender_name'       => 'NorthOps Team',
        ]);

        $this->assertStringContainsString('NorthOps', $outreach['body']);
        $this->assertStringNotContainsString('Web Networks', $outreach['body']);
        $this->assertSame('northops', $outreach['brand']);
    }
}
