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

    public function testUnknownSectorReturnsNull(): void
    {
        $this->assertNull(SectorNormalizer::normalize('Healthcare'));
        $this->assertNull(SectorNormalizer::normalize('Finance'));
        $this->assertNull(SectorNormalizer::normalize('random-text'));
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
}
