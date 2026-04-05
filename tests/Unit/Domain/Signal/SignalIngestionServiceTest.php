<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Signal;

use App\Domain\Pipeline\LeadFactoryInterface;
use App\Domain\Signal\SignalIngestionService;
use App\Domain\Signal\SignalMatcherInterface;
use App\Entity\Lead;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class SignalIngestionServiceTest extends TestCase
{
    private function validSignal(array $overrides = []): array
    {
        return array_merge([
            'signal_type' => 'rfp',
            'external_id' => 'nc-rfp-1',
            'source' => 'north-cloud',
            'label' => 'Test RFP Signal',
            'strength' => 75,
            'organization_name' => 'Test Org',
        ], $overrides);
    }

    public function testValidSignalIsIngested(): void
    {
        $service = $this->buildService(duplicateExists: false);
        $result = $service->ingest([$this->validSignal()]);
        $this->assertSame(1, $result->ingested);
        $this->assertSame(0, $result->skipped);
    }

    public function testDuplicateExternalIdIsSkipped(): void
    {
        $service = $this->buildService(duplicateExists: true);
        $result = $service->ingest([$this->validSignal()]);
        $this->assertSame(0, $result->ingested);
        $this->assertSame(1, $result->skipped);
    }

    public function testMissingRequiredFieldReturnsError(): void
    {
        $service = $this->buildService(duplicateExists: false);
        $result = $service->ingest([['signal_type' => 'rfp']]);
        $this->assertSame(0, $result->ingested);
        $this->assertNotEmpty($result->errors);
    }

    public function testHighStrengthAutoCreatesLead(): void
    {
        $service = $this->buildService(duplicateExists: false, matchResult: null);
        $result = $service->ingest([$this->validSignal(['strength' => 80])]);
        $this->assertSame(1, $result->ingested);
        $this->assertSame(1, $result->leadsCreated);
    }

    public function testLowStrengthStoresUnmatched(): void
    {
        $service = $this->buildService(duplicateExists: false, matchResult: null);
        $result = $service->ingest([$this->validSignal(['strength' => 30])]);
        $this->assertSame(1, $result->ingested);
        $this->assertSame(1, $result->unmatched);
        $this->assertSame(0, $result->leadsCreated);
    }

    public function testMatchedSignalLinksToLead(): void
    {
        $lead = new Lead(['label' => 'Existing Lead']);
        $service = $this->buildService(duplicateExists: false, matchResult: $lead);
        $result = $service->ingest([$this->validSignal()]);
        $this->assertSame(1, $result->ingested);
        $this->assertSame(1, $result->leadsMatched);
    }

    public function testInvalidSignalTypeReturnsError(): void
    {
        $service = $this->buildService(duplicateExists: false);
        $result = $service->ingest([$this->validSignal(['signal_type' => 'invalid'])]);
        $this->assertSame(0, $result->ingested);
        $this->assertNotEmpty($result->errors);
    }

    private function buildService(bool $duplicateExists, ?Lead $matchResult = null): SignalIngestionService
    {
        // Build a query mock that returns IDs based on whether duplicate exists
        $mockQuery = $this->createMock(\Waaseyaa\Entity\Storage\EntityQueryInterface::class);
        $mockQuery->method('condition')->willReturnSelf();
        $mockQuery->method('execute')->willReturn($duplicateExists ? [99] : []);

        $mockStorage = $this->createMock(EntityStorageInterface::class);
        $mockStorage->method('getQuery')->willReturn($mockQuery);
        $mockStorage->method('save')->willReturn(1);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($mockStorage);

        $matcher = $this->createMock(SignalMatcherInterface::class);
        $matcher->method('match')->willReturn($matchResult);

        $factory = $this->createMock(LeadFactoryInterface::class);
        $factory->method('fromSignal')->willReturn(new Lead(['label' => 'Auto-created']));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        return new SignalIngestionService($etm, $matcher, $factory, $dispatcher, 50, 1);
    }
}
