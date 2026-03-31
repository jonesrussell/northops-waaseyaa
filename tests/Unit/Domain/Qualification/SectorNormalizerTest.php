<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Qualification;

use App\Domain\Qualification\SectorNormalizer;
use PHPUnit\Framework\TestCase;

final class SectorNormalizerTest extends TestCase
{
    public function testExactMatch(): void
    {
        $this->assertSame('IT', SectorNormalizer::normalize('IT'));
        $this->assertSame('Cloud', SectorNormalizer::normalize('Cloud'));
        $this->assertSame('DevOps', SectorNormalizer::normalize('DevOps'));
        $this->assertSame('AI', SectorNormalizer::normalize('AI'));
    }

    public function testCaseInsensitiveMatch(): void
    {
        $this->assertSame('IT', SectorNormalizer::normalize('it'));
        $this->assertSame('Cloud', SectorNormalizer::normalize('CLOUD'));
        $this->assertSame('DevOps', SectorNormalizer::normalize('devops'));
        $this->assertSame('Security', SectorNormalizer::normalize('security'));
    }

    public function testTrimsWhitespace(): void
    {
        $this->assertSame('IT', SectorNormalizer::normalize('  IT  '));
        $this->assertSame('Cloud', SectorNormalizer::normalize(' Cloud'));
    }

    public function testNullInput(): void
    {
        $this->assertNull(SectorNormalizer::normalize(null));
    }

    public function testEmptyString(): void
    {
        $this->assertNull(SectorNormalizer::normalize(''));
        $this->assertNull(SectorNormalizer::normalize('   '));
    }

    public function testUnknownSectorReturnsOther(): void
    {
        $this->assertSame('Other', SectorNormalizer::normalize('Healthcare'));
        $this->assertSame('Other', SectorNormalizer::normalize('Finance'));
        $this->assertSame('Other', SectorNormalizer::normalize('random-text'));
    }

    public function testAllSectorsNormalize(): void
    {
        foreach (SectorNormalizer::SECTORS as $sector) {
            $this->assertSame(
                $sector,
                SectorNormalizer::normalize($sector),
                "Sector '{$sector}' should normalize to itself",
            );
        }
    }

    // --- Organization type detection ---

    public function testDetectsIndigenousOrg(): void
    {
        $this->assertSame('indigenous', SectorNormalizer::detectOrganizationType('First Nation community'));
        $this->assertSame('indigenous', SectorNormalizer::detectOrganizationType('Band Council'));
        $this->assertSame('indigenous', SectorNormalizer::detectOrganizationType('Metis organization'));
        $this->assertSame('indigenous', SectorNormalizer::detectOrganizationType('Inuit services'));
    }

    public function testDetectsNonProfit(): void
    {
        $this->assertSame('non_profit', SectorNormalizer::detectOrganizationType('Non-profit housing'));
        $this->assertSame('non_profit', SectorNormalizer::detectOrganizationType('Nonprofit org'));
        $this->assertSame('non_profit', SectorNormalizer::detectOrganizationType('Community foundation'));
        $this->assertSame('non_profit', SectorNormalizer::detectOrganizationType('501c3 organization'));
    }

    public function testDetectsCharity(): void
    {
        $this->assertSame('charity', SectorNormalizer::detectOrganizationType('Registered charity'));
        $this->assertSame('charity', SectorNormalizer::detectOrganizationType('Local charity'));
    }

    public function testDetectsCommunity(): void
    {
        $this->assertSame('community', SectorNormalizer::detectOrganizationType('Community centre'));
        $this->assertSame('community', SectorNormalizer::detectOrganizationType('Housing co-op'));
        $this->assertSame('community', SectorNormalizer::detectOrganizationType('Cooperative'));
        $this->assertSame('community', SectorNormalizer::detectOrganizationType('Business association'));
    }

    public function testDetectsGovernment(): void
    {
        $this->assertSame('government', SectorNormalizer::detectOrganizationType('Municipal services'));
        $this->assertSame('government', SectorNormalizer::detectOrganizationType('Government of Canada'));
        $this->assertSame('government', SectorNormalizer::detectOrganizationType('Public sector IT'));
        $this->assertSame('government', SectorNormalizer::detectOrganizationType('Crown corporation'));
    }

    public function testDetectsStartup(): void
    {
        $this->assertSame('startup', SectorNormalizer::detectOrganizationType('SaaS platform'));
        $this->assertSame('startup', SectorNormalizer::detectOrganizationType('Series A funded'));
        $this->assertSame('startup', SectorNormalizer::detectOrganizationType('Tech startup'));
        $this->assertSame('startup', SectorNormalizer::detectOrganizationType('Seed stage'));
    }

    public function testIndigenousTakesPriorityOverNonProfit(): void
    {
        $this->assertSame('indigenous', SectorNormalizer::detectOrganizationType('Indigenous non-profit'));
    }

    public function testNonProfitTakesPriorityOverCharity(): void
    {
        $this->assertSame('non_profit', SectorNormalizer::detectOrganizationType('Non-profit charity'));
    }

    public function testNoMatchReturnsNull(): void
    {
        $this->assertNull(SectorNormalizer::detectOrganizationType('Random company'));
        $this->assertNull(SectorNormalizer::detectOrganizationType(null));
        $this->assertNull(SectorNormalizer::detectOrganizationType(''));
    }
}
