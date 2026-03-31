<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\RfpImportService;
use PHPUnit\Framework\TestCase;

final class RfpImportServiceTest extends TestCase
{
    public function testImportReturnsErrorWhenNorthcloudUrlEmpty(): void
    {
        // Use reflection to create a minimal instance without real dependencies.
        // RfpImportService only needs the URL to be empty to trigger the error path.
        $ref = new \ReflectionClass(RfpImportService::class);
        $service = $ref->newInstanceWithoutConstructor();

        // Set private properties directly.
        $urlProp = $ref->getProperty('northcloudUrl');
        $urlProp->setValue($service, '');

        $stats = $service->import(1, 7);

        $this->assertSame(1, $stats['errors']);
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, $stats['skipped']);
    }

    public function testMapHitToRfpDataFallsBackToSourceUrl(): void
    {
        $ref = new \ReflectionClass(RfpImportService::class);
        $service = $ref->newInstanceWithoutConstructor();

        $method = $ref->getMethod('mapHitToRfpData');

        // canonical_url present
        $result = $method->invoke($service, ['canonical_url' => 'https://example.com/rfp/1']);
        $this->assertSame('https://example.com/rfp/1', $result['source_url']);

        // canonical_url absent, source present
        $result = $method->invoke($service, ['source' => 'https://canadabuys.ca/rfp/2']);
        $this->assertSame('https://canadabuys.ca/rfp/2', $result['source_url']);

        // both absent, url present
        $result = $method->invoke($service, ['url' => 'https://fallback.ca/3']);
        $this->assertSame('https://fallback.ca/3', $result['source_url']);

        // all absent
        $result = $method->invoke($service, []);
        $this->assertSame('', $result['source_url']);
    }
}
