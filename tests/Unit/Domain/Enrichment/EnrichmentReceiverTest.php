<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enrichment;

use App\Domain\Enrichment\EnrichmentReceiver;
use App\Entity\Lead;
use App\Entity\LeadEnrichment;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class EnrichmentReceiverTest extends TestCase
{
    public function testReceiveCreatesEnrichment(): void
    {
        $receiver = $this->buildReceiver();
        $lead = new Lead(['label' => 'Test Lead']);

        $enrichment = $receiver->receive($lead, [
            'provider' => 'north-cloud',
            'enrichment_type' => 'company_intel',
            'data' => ['website' => 'https://example.com'],
            'confidence' => 0.85,
        ]);

        $this->assertInstanceOf(LeadEnrichment::class, $enrichment);
        $this->assertSame('north-cloud', $enrichment->getProvider());
        $this->assertSame('company_intel', $enrichment->getEnrichmentType());
        $this->assertSame(0.85, $enrichment->getConfidence());
    }

    public function testReceiveRejectsMissingProvider(): void
    {
        $receiver = $this->buildReceiver();
        $lead = new Lead(['label' => 'Test Lead']);

        $this->expectException(\InvalidArgumentException::class);
        $receiver->receive($lead, [
            'enrichment_type' => 'company_intel',
            'data' => [],
            'confidence' => 0.5,
        ]);
    }

    public function testReceiveRejectsInvalidEnrichmentType(): void
    {
        $receiver = $this->buildReceiver();
        $lead = new Lead(['label' => 'Test Lead']);

        $this->expectException(\InvalidArgumentException::class);
        $receiver->receive($lead, [
            'provider' => 'north-cloud',
            'enrichment_type' => 'invalid_type',
            'data' => [],
            'confidence' => 0.5,
        ]);
    }

    private function buildReceiver(): EnrichmentReceiver
    {
        $mockStorage = $this->createMock(EntityStorageInterface::class);
        $mockStorage->method('save')->willReturn(1);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($mockStorage);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        return new EnrichmentReceiver($etm, $dispatcher);
    }
}
