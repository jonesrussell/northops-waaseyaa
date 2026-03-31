<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\Lead;
use PHPUnit\Framework\TestCase;

final class LeadTest extends TestCase
{
    public function testNewFieldsStoredAndRetrieved(): void
    {
        $lead = new Lead([
            'label' => 'Test Lead',
            'budget_range' => '10k_25k',
            'urgency' => 'high',
            'tier' => 'T2',
            'organization_type' => 'nonprofit',
            'funding_status' => 'received',
            'routing_confidence' => 85,
            'lead_source' => 'rfp',
            'last_scored_at' => '2026-03-30T12:00:00+00:00',
            'specialist_context' => '{"specialists":["web-dev"],"reasoning":{"match":"strong"}}',
        ]);

        $this->assertSame('10k_25k', $lead->getBudgetRange());
        $this->assertSame('high', $lead->getUrgency());
        $this->assertSame('T2', $lead->getTier());
        $this->assertSame('nonprofit', $lead->getOrganizationType());
        $this->assertSame('received', $lead->getFundingStatus());
        $this->assertSame(85, $lead->getRoutingConfidence());
        $this->assertSame('rfp', $lead->getLeadSource());
        $this->assertSame('2026-03-30T12:00:00+00:00', $lead->getLastScoredAt());
        $this->assertSame('{"specialists":["web-dev"],"reasoning":{"match":"strong"}}', $lead->getSpecialistContext());
    }

    public function testNewFieldsDefaultToEmpty(): void
    {
        $lead = new Lead(['label' => 'Minimal Lead']);

        $this->assertSame('', $lead->getBudgetRange());
        $this->assertSame('', $lead->getUrgency());
        $this->assertSame('', $lead->getTier());
        $this->assertSame('', $lead->getOrganizationType());
        $this->assertSame('', $lead->getFundingStatus());
        $this->assertSame(0, $lead->getRoutingConfidence());
        $this->assertSame('', $lead->getLeadSource());
        $this->assertSame('', $lead->getLastScoredAt());
        $this->assertSame('', $lead->getSpecialistContext());
    }
}
