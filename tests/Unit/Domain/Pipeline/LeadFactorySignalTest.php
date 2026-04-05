<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Pipeline\RoutingService;
use App\Entity\Lead;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadFactorySignalTest extends TestCase
{
    public function testFromSignalMapsRfpFields(): void
    {
        $factory = $this->createFactory();

        $lead = $factory->fromSignal([
            'label' => 'Web App Dev RFP',
            'organization_name' => 'Health Canada',
            'source_url' => 'https://canadabuys.canada.ca/123',
            'external_id' => 'nc-rfp-456',
            'sector' => 'government',
            'signal_type' => 'rfp',
            'strength' => 75,
        ], 1);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertSame('Web App Dev RFP', $lead->getLabel());
        $this->assertSame('Health Canada', $lead->getCompanyName());
        $this->assertSame('https://canadabuys.canada.ca/123', $lead->getSourceUrl());
        $this->assertSame('nc-rfp-456', $lead->getExternalId());
        $this->assertSame('government', $lead->getSector());
        $this->assertSame('rfp', $lead->getSource());
    }

    public function testFromSignalMapsFundingToReferral(): void
    {
        $factory = $this->createFactory();
        $lead = $factory->fromSignal([
            'label' => 'Funding win',
            'signal_type' => 'funding_win',
            'external_id' => 'nc-sig-789',
        ], 1);
        $this->assertSame('referral', $lead->getSource());
    }

    public function testFromSignalMapsJobPostingToColdOutreach(): void
    {
        $factory = $this->createFactory();
        $lead = $factory->fromSignal([
            'label' => 'Hiring signal',
            'signal_type' => 'job_posting',
            'external_id' => 'nc-sig-101',
        ], 1);
        $this->assertSame('cold_outreach', $lead->getSource());
    }

    public function testFromSignalMapsUnknownTypeToOther(): void
    {
        $factory = $this->createFactory();
        $lead = $factory->fromSignal([
            'label' => 'HN mention',
            'signal_type' => 'hn_mention',
            'external_id' => 'hn-202',
        ], 1);
        $this->assertSame('other', $lead->getSource());
    }

    private function createFactory(): LeadFactory
    {
        $mockStorage = $this->createMock(\Waaseyaa\Entity\Storage\EntityStorageInterface::class);
        $mockStorage->method('save')->willReturn(1);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($mockStorage);

        $leadManager = new LeadManager($etm);
        $routingService = new RoutingService();

        return new LeadFactory($leadManager, $etm, $routingService);
    }
}
