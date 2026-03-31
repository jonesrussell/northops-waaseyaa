<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\ProspectScoringService;
use PHPUnit\Framework\TestCase;

final class ProspectScoringServiceTest extends TestCase
{
    private ProspectScoringService $service;

    protected function setUp(): void
    {
        $this->service = new ProspectScoringService();
    }

    public function testHighScoreForStrongFit(): void
    {
        $result = $this->service->score([
            'sector' => 'IT',
            'qualify_rating' => 9,
            'value' => 120_000.0,
            'closing_date' => (new \DateTimeImmutable('+14 days'))->format('Y-m-d'),
            'signal_type' => 'outdated_website',
            'org_type' => 'non_profit',
        ]);

        // sector=25 + qualification=23 + value=20 + urgency=15 + signal=15 = 98
        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertSame('webnet', $result['recommended_brand']);
    }

    public function testLowScoreForWeakFit(): void
    {
        $result = $this->service->score([
            'sector' => 'Other',
            'qualify_rating' => 2,
            'value' => 3_000.0,
            'closing_date' => (new \DateTimeImmutable('-5 days'))->format('Y-m-d'),
            'signal_type' => null,
        ]);

        // sector=5 + qualification=5 + value=2 + urgency=0 + signal=0 = 12
        $this->assertLessThan(30, $result['score']);
    }

    public function testBrandRoutingNonProfit(): void
    {
        $result = $this->service->score([
            'org_type' => 'non_profit',
        ]);

        $this->assertSame('webnet', $result['recommended_brand']);
    }

    public function testBrandRoutingCommercial(): void
    {
        $result = $this->service->score([
            'org_type' => 'commercial',
        ]);

        $this->assertSame('northops', $result['recommended_brand']);
    }

    public function testScoreBreakdownIncluded(): void
    {
        $result = $this->service->score([
            'sector' => 'Cloud',
            'qualify_rating' => 7,
            'value' => 50_000.0,
            'closing_date' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d'),
            'signal_type' => 'job_posting',
            'org_type' => 'government',
        ]);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('recommended_brand', $result);
        $this->assertArrayHasKey('tier', $result);
        $this->assertArrayHasKey('last_scored_at', $result);

        $breakdown = $result['breakdown'];
        $this->assertArrayHasKey('sector', $breakdown);
        $this->assertArrayHasKey('qualification', $breakdown);
        $this->assertArrayHasKey('value', $breakdown);
        $this->assertArrayHasKey('urgency', $breakdown);
        $this->assertArrayHasKey('signal', $breakdown);

        // Verify score equals sum of breakdown
        $this->assertSame(
            (int) array_sum($breakdown),
            $result['score'],
        );
    }

    public function testTierT1ForHighScore(): void
    {
        $result = $this->service->score([
            'sector' => 'IT',
            'qualify_rating' => 100,
            'value' => 120_000.0,
            'closing_date' => (new \DateTimeImmutable('+14 days'))->format('Y-m-d'),
            'signal_type' => 'outdated_website',
        ]);

        $this->assertGreaterThanOrEqual(80, $result['score']);
        $this->assertSame('T1', $result['tier']);
    }

    public function testTierT3ForLowScore(): void
    {
        $result = $this->service->score([
            'sector' => 'Other',
            'qualify_rating' => 2,
            'value' => 3_000.0,
            'closing_date' => (new \DateTimeImmutable('-5 days'))->format('Y-m-d'),
        ]);

        $this->assertLessThan(50, $result['score']);
        $this->assertSame('T3', $result['tier']);
    }

    public function testLastScoredAtIsSet(): void
    {
        $result = $this->service->score([]);

        $this->assertNotEmpty($result['last_scored_at']);
        // Should be a valid ISO 8601 date
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $result['last_scored_at']);
        $this->assertNotFalse($parsed);
    }

    public function testTierFromScoreBoundaries(): void
    {
        $this->assertSame('T1', ProspectScoringService::tierFromScore(100));
        $this->assertSame('T1', ProspectScoringService::tierFromScore(80));
        $this->assertSame('T2', ProspectScoringService::tierFromScore(79));
        $this->assertSame('T2', ProspectScoringService::tierFromScore(50));
        $this->assertSame('T3', ProspectScoringService::tierFromScore(49));
        $this->assertSame('T3', ProspectScoringService::tierFromScore(0));
    }

    public function testBrandRoutingTextHeuristic(): void
    {
        $result = $this->service->score([
            'title' => 'Website redesign for municipality of Sudbury',
            'description' => 'Complete overhaul of municipal services portal.',
        ]);

        $this->assertSame('webnet', $result['recommended_brand']);
    }
}
