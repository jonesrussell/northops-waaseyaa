<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\DecayScoresCommand;
use PHPUnit\Framework\TestCase;

final class DecayScoresCommandTest extends TestCase
{
    // --- NorthOps decay rates ---

    public function testNorthopsNoDecayUnder14Days(): void
    {
        $this->assertSame(0, DecayScoresCommand::calculateDecay('northops', 13));
        $this->assertSame(0, DecayScoresCommand::calculateDecay('northops', 0));
    }

    public function testNorthops14DayDecay(): void
    {
        $this->assertSame(5, DecayScoresCommand::calculateDecay('northops', 14));
        $this->assertSame(5, DecayScoresCommand::calculateDecay('northops', 29));
    }

    public function testNorthops30DayDecay(): void
    {
        $this->assertSame(10, DecayScoresCommand::calculateDecay('northops', 30));
        $this->assertSame(10, DecayScoresCommand::calculateDecay('northops', 59));
    }

    public function testNorthops60DayDecay(): void
    {
        $this->assertSame(20, DecayScoresCommand::calculateDecay('northops', 60));
        $this->assertSame(20, DecayScoresCommand::calculateDecay('northops', 120));
    }

    // --- Web Networks decay rates ---

    public function testWebnetNoDecayUnder30Days(): void
    {
        $this->assertSame(0, DecayScoresCommand::calculateDecay('webnet', 14));
        $this->assertSame(0, DecayScoresCommand::calculateDecay('webnet', 29));
    }

    public function testWebnet30DayDecay(): void
    {
        $this->assertSame(3, DecayScoresCommand::calculateDecay('webnet', 30));
        $this->assertSame(3, DecayScoresCommand::calculateDecay('webnet', 59));
    }

    public function testWebnet60DayDecay(): void
    {
        $this->assertSame(8, DecayScoresCommand::calculateDecay('webnet', 60));
        $this->assertSame(8, DecayScoresCommand::calculateDecay('webnet', 89));
    }

    public function testWebnet90DayDecay(): void
    {
        $this->assertSame(15, DecayScoresCommand::calculateDecay('webnet', 90));
        $this->assertSame(15, DecayScoresCommand::calculateDecay('webnet', 180));
    }

    // --- Largest applicable, not cumulative ---

    public function testDecayIsLargestNotCumulative(): void
    {
        // At 60 days for NorthOps, penalty should be 20 (not 5+10+20=35)
        $this->assertSame(20, DecayScoresCommand::calculateDecay('northops', 60));
    }

    // --- Unknown brand falls back to NorthOps rates ---

    public function testUnknownBrandUsesNorthopsRates(): void
    {
        $this->assertSame(5, DecayScoresCommand::calculateDecay('unknown', 14));
        $this->assertSame(20, DecayScoresCommand::calculateDecay('unknown', 60));
    }

    // --- Tier calculation ---

    public function testTierFromScore(): void
    {
        $this->assertSame('T1', DecayScoresCommand::tierFromScore(80));
        $this->assertSame('T1', DecayScoresCommand::tierFromScore(100));
        $this->assertSame('T2', DecayScoresCommand::tierFromScore(50));
        $this->assertSame('T2', DecayScoresCommand::tierFromScore(79));
        $this->assertSame('T3', DecayScoresCommand::tierFromScore(49));
        $this->assertSame('T3', DecayScoresCommand::tierFromScore(0));
    }

    // --- Days since calculation ---

    public function testDaysSinceRecentDate(): void
    {
        $yesterday = (new \DateTimeImmutable('-1 day'))->format('c');
        $this->assertSame(1, DecayScoresCommand::daysSince($yesterday));
    }

    public function testDaysSinceInvalidDateReturnsZero(): void
    {
        $this->assertSame(0, DecayScoresCommand::daysSince('not-a-date'));
    }

    // --- Score floor at 0 ---

    public function testDecayCannotExceedScore(): void
    {
        // Score of 3 with 20-point penalty should floor at 0
        $this->assertSame(0, max(0, 3 - DecayScoresCommand::calculateDecay('northops', 60)));
    }
}
