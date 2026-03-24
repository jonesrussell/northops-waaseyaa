<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\RfpImportService;
use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;

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
}
