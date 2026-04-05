<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\LeadSignal;
use PHPUnit\Framework\TestCase;

final class LeadSignalTest extends TestCase
{
    public function testFieldsStoredAndRetrieved(): void
    {
        $signal = new LeadSignal([
            'label' => 'Web App Dev RFP',
            'lead_id' => 42,
            'signal_type' => 'rfp',
            'source' => 'north-cloud',
            'source_url' => 'https://canadabuys.canada.ca/123',
            'external_id' => 'nc-rfp-456',
            'strength' => 75,
            'payload' => ['title' => 'Test', 'score' => 80],
            'organization_name' => 'Health Canada',
            'sector' => 'government',
            'province' => 'ON',
            'expires_at' => '2026-05-15T00:00:00Z',
        ]);

        $this->assertSame('Web App Dev RFP', $signal->getLabel());
        $this->assertSame(42, $signal->getLeadId());
        $this->assertSame('rfp', $signal->getSignalType());
        $this->assertSame('north-cloud', $signal->getSource());
        $this->assertSame('https://canadabuys.canada.ca/123', $signal->getSourceUrl());
        $this->assertSame('nc-rfp-456', $signal->getExternalId());
        $this->assertSame(75, $signal->getStrength());
        $this->assertSame(['title' => 'Test', 'score' => 80], $signal->getPayload());
        $this->assertSame('Health Canada', $signal->getOrganizationName());
        $this->assertSame('government', $signal->getSector());
        $this->assertSame('ON', $signal->getProvince());
        $this->assertSame('2026-05-15T00:00:00Z', $signal->getExpiresAt());
        $this->assertNotEmpty($signal->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $signal = new LeadSignal([
            'label' => 'Minimal signal',
            'signal_type' => 'hn_mention',
            'source' => 'signal-crawler',
            'external_id' => 'hn-789',
        ]);

        $this->assertNull($signal->getLeadId());
        $this->assertSame('', $signal->getSourceUrl());
        $this->assertSame(50, $signal->getStrength());
        $this->assertSame([], $signal->getPayload());
        $this->assertSame('', $signal->getOrganizationName());
        $this->assertSame('', $signal->getSector());
        $this->assertSame('', $signal->getProvince());
        $this->assertNull($signal->getExpiresAt());
        $this->assertNotEmpty($signal->getCreatedAt());
    }
}
