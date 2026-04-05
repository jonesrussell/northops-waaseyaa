<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Signal;

use App\Domain\Signal\SignalMatcher;
use PHPUnit\Framework\TestCase;

final class SignalMatcherTest extends TestCase
{
    public function testNormalizeOrgNameStripsInc(): void
    {
        $this->assertSame('health canada', SignalMatcher::normalizeOrgName('Health Canada Inc.'));
    }

    public function testNormalizeOrgNameStripsLtd(): void
    {
        $this->assertSame('acme solutions', SignalMatcher::normalizeOrgName('Acme Solutions Ltd'));
    }

    public function testNormalizeOrgNameStripsCorp(): void
    {
        $this->assertSame('big tech', SignalMatcher::normalizeOrgName('Big Tech Corp.'));
    }

    public function testNormalizeOrgNameStripsLlc(): void
    {
        $this->assertSame('startup co', SignalMatcher::normalizeOrgName('Startup Co LLC'));
    }

    public function testNormalizeOrgNameStripsLimited(): void
    {
        $this->assertSame('northern services', SignalMatcher::normalizeOrgName('Northern Services Limited'));
    }

    public function testNormalizeOrgNameStripsIncorporated(): void
    {
        $this->assertSame('megacorp', SignalMatcher::normalizeOrgName('MegaCorp Incorporated'));
    }

    public function testNormalizeOrgNameStripsCorporation(): void
    {
        $this->assertSame('global', SignalMatcher::normalizeOrgName('Global Corporation'));
    }

    public function testNormalizeOrgNameHandlesNoSuffix(): void
    {
        $this->assertSame('health canada', SignalMatcher::normalizeOrgName('Health Canada'));
    }

    public function testNormalizeOrgNameTrimsWhitespace(): void
    {
        $this->assertSame('health canada', SignalMatcher::normalizeOrgName('  Health Canada  '));
    }

    public function testNormalizeOrgNameHandlesEmpty(): void
    {
        $this->assertSame('', SignalMatcher::normalizeOrgName(''));
    }
}
