<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\OutreachListCommand;
use App\Domain\Pipeline\OutreachTemplateRenderer;
use App\Entity\Brand;
use App\Entity\Lead;
use App\Entity\Outreach;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class OutreachListCommandTest extends TestCase
{
    private EntityTypeManager&MockObject $entityTypeManager;
    private OutreachTemplateRenderer $renderer;
    private OutreachListCommand $command;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->renderer = new OutreachTemplateRenderer();
        $this->command = new OutreachListCommand($this->entityTypeManager, $this->renderer);
    }

    public function testListLeadsNoResults(): void
    {
        $leadQuery = $this->createMock(EntityQueryInterface::class);
        $leadQuery->method('condition')->willReturnSelf();
        $leadQuery->method('sort')->willReturnSelf();
        $leadQuery->method('execute')->willReturn([]);

        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->method('getQuery')->willReturn($leadQuery);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['lead', $leadStorage],
            ]);

        $input = new ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $output = new BufferedOutput();

        $this->command->run($input, $output);

        $this->assertStringContainsString('No qualified leads found', $output->fetch());
    }

    public function testListLeadsWithResults(): void
    {
        $lead = new Lead([
            'id'             => 1,
            'uuid'           => 'lead-uuid-001',
            'label'          => 'Maple Health',
            'stage'          => 'qualified',
            'qualify_rating' => 75,
            'sector'         => 'healthcare',
            'source'         => 'outdated_website',
            'contact_email'  => 'info@maplehealth.ca',
            'brand_id'       => 1,
        ]);

        $brand = new Brand([
            'id'   => 1,
            'uuid' => 'brand-uuid-001',
            'name' => 'NorthOps',
            'slug' => 'northops',
        ]);

        $leadQuery = $this->createMock(EntityQueryInterface::class);
        $leadQuery->method('condition')->willReturnSelf();
        $leadQuery->method('sort')->willReturnSelf();
        $leadQuery->method('execute')->willReturn([1]);

        $outreachQuery = $this->createMock(EntityQueryInterface::class);
        $outreachQuery->method('execute')->willReturn([]);

        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->method('getQuery')->willReturn($leadQuery);
        $leadStorage->method('loadMultiple')->with([1])->willReturn([1 => $lead]);

        $outreachStorage = $this->createMock(EntityStorageInterface::class);
        $outreachStorage->method('getQuery')->willReturn($outreachQuery);
        $outreachStorage->method('loadMultiple')->willReturn([]);

        $brandStorage = $this->createMock(EntityStorageInterface::class);
        $brandStorage->method('load')->with(1)->willReturn($brand);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['lead', $leadStorage],
                ['outreach', $outreachStorage],
                ['brand', $brandStorage],
            ]);

        $input = new ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);
        $content = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Maple Health', $content);
        $this->assertStringContainsString('75', $content);
        $this->assertStringContainsString('northops', $content);
        $this->assertStringContainsString('healthcare', $content);
        $this->assertStringContainsString('1 lead(s) ready for outreach', $content);
    }

    public function testListExcludesLeadsWithExistingOutreach(): void
    {
        $lead = new Lead([
            'id'             => 2,
            'uuid'           => 'lead-uuid-002',
            'label'          => 'Already Contacted',
            'stage'          => 'qualified',
            'qualify_rating' => 80,
            'brand_id'       => 0,
        ]);

        $outreach = new Outreach([
            'id'        => 10,
            'uuid'      => 'outreach-uuid-001',
            'lead_uuid' => 'lead-uuid-002',
        ]);

        $leadQuery = $this->createMock(EntityQueryInterface::class);
        $leadQuery->method('condition')->willReturnSelf();
        $leadQuery->method('sort')->willReturnSelf();
        $leadQuery->method('execute')->willReturn([2]);

        $outreachQuery = $this->createMock(EntityQueryInterface::class);
        $outreachQuery->method('execute')->willReturn([10]);

        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->method('getQuery')->willReturn($leadQuery);
        $leadStorage->method('loadMultiple')->with([2])->willReturn([2 => $lead]);

        $outreachStorage = $this->createMock(EntityStorageInterface::class);
        $outreachStorage->method('getQuery')->willReturn($outreachQuery);
        $outreachStorage->method('loadMultiple')->with([10])->willReturn([10 => $outreach]);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['lead', $leadStorage],
                ['outreach', $outreachStorage],
            ]);

        $input = new ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $output = new BufferedOutput();

        $this->command->run($input, $output);
        $content = $output->fetch();

        $this->assertStringContainsString('No leads require outreach', $content);
    }

    public function testPreviewEmailForValidLead(): void
    {
        $lead = new Lead([
            'id'             => 3,
            'uuid'           => 'preview-uuid-001',
            'label'          => 'TechCo',
            'company_name'   => 'TechCo Inc',
            'stage'          => 'qualified',
            'qualify_rating' => 90,
            'source'         => 'job_posting',
            'contact_name'   => 'David',
            'qualify_notes'  => 'hiring a senior web developer',
            'brand_id'       => 0,
        ]);

        $previewQuery = $this->createMock(EntityQueryInterface::class);
        $previewQuery->method('condition')->willReturnSelf();
        $previewQuery->method('execute')->willReturn([3]);

        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->method('getQuery')->willReturn($previewQuery);
        $leadStorage->method('load')->with(3)->willReturn($lead);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['lead', $leadStorage],
            ]);

        $input = new ArrayInput(['--preview' => 'preview-uuid-001']);
        $input->bind($this->command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);
        $content = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Subject:', $content);
        $this->assertStringContainsString('TechCo', $content);
        $this->assertStringContainsString('David', $content);
        $this->assertStringContainsString('hiring a senior web developer', $content);
    }

    public function testPreviewEmailForUnknownUuid(): void
    {
        $previewQuery = $this->createMock(EntityQueryInterface::class);
        $previewQuery->method('condition')->willReturnSelf();
        $previewQuery->method('execute')->willReturn([]);

        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->method('getQuery')->willReturn($previewQuery);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['lead', $leadStorage],
            ]);

        $input = new ArrayInput(['--preview' => 'nonexistent-uuid']);
        $input->bind($this->command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not found', $output->fetch());
    }

    public function testBrandFilterApplied(): void
    {
        $lead = new Lead([
            'id'             => 4,
            'uuid'           => 'lead-uuid-004',
            'label'          => 'Wrong Brand Co',
            'stage'          => 'qualified',
            'qualify_rating' => 60,
            'brand_id'       => 2,
        ]);

        $brand = new Brand([
            'id'   => 2,
            'uuid' => 'brand-uuid-002',
            'name' => 'Web Networks',
            'slug' => 'web-networks',
        ]);

        $leadQuery = $this->createMock(EntityQueryInterface::class);
        $leadQuery->method('condition')->willReturnSelf();
        $leadQuery->method('sort')->willReturnSelf();
        $leadQuery->method('execute')->willReturn([4]);

        $outreachQuery = $this->createMock(EntityQueryInterface::class);
        $outreachQuery->method('execute')->willReturn([]);

        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->method('getQuery')->willReturn($leadQuery);
        $leadStorage->method('loadMultiple')->willReturn([4 => $lead]);

        $outreachStorage = $this->createMock(EntityStorageInterface::class);
        $outreachStorage->method('getQuery')->willReturn($outreachQuery);
        $outreachStorage->method('loadMultiple')->willReturn([]);

        $brandStorage = $this->createMock(EntityStorageInterface::class);
        $brandStorage->method('load')->with(2)->willReturn($brand);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['lead', $leadStorage],
                ['outreach', $outreachStorage],
                ['brand', $brandStorage],
            ]);

        // Filter by northops — should exclude the web-networks lead
        $input = new ArrayInput(['--brand' => 'northops']);
        $input->bind($this->command->getDefinition());
        $output = new BufferedOutput();

        $this->command->run($input, $output);
        $content = $output->fetch();

        $this->assertStringContainsString('No leads require outreach', $content);
    }
}
