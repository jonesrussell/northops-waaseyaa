<?php

declare(strict_types=1);

namespace Tests\Unit\Surface\Action;

use App\Entity\Lead;
use App\Surface\Action\LeadTransitionStageAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(LeadTransitionStageAction::class)]
final class LeadTransitionStageActionTest extends TestCase
{
    private EntityTypeManager&MockObject $entityTypeManager;
    private EntityStorageInterface&MockObject $storage;
    private LeadTransitionStageAction $action;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->storage = $this->createMock(EntityStorageInterface::class);

        $this->entityTypeManager->method('getStorage')
            ->with('lead')
            ->willReturn($this->storage);

        $this->action = new LeadTransitionStageAction($this->entityTypeManager);
    }

    #[Test]
    public function handle_returns_error_when_id_is_missing(): void
    {
        $result = $this->action->handle('lead', ['stage' => 'qualified']);

        self::assertFalse($result->ok);
        self::assertSame(400, $result->error['status']);
        self::assertStringContainsString('id', $result->error['detail']);
    }

    #[Test]
    public function handle_returns_error_when_stage_is_missing(): void
    {
        $result = $this->action->handle('lead', ['id' => '1']);

        self::assertFalse($result->ok);
        self::assertSame(400, $result->error['status']);
        self::assertStringContainsString('stage', $result->error['detail']);
    }

    #[Test]
    public function handle_returns_error_when_lead_not_found(): void
    {
        $this->storage->method('load')->with('99')->willReturn(null);

        $result = $this->action->handle('lead', ['id' => '99', 'stage' => 'qualified']);

        self::assertFalse($result->ok);
        self::assertSame(404, $result->error['status']);
    }

    #[Test]
    public function handle_returns_error_for_invalid_stage(): void
    {
        $result = $this->action->handle('lead', ['id' => '1', 'stage' => 'nonexistent']);

        self::assertFalse($result->ok);
        self::assertSame(422, $result->error['status']);
        self::assertStringContainsString('nonexistent', $result->error['detail']);
    }

    #[Test]
    public function handle_returns_error_for_invalid_transition(): void
    {
        $lead = new Lead(['id' => 1, 'label' => 'Test Lead', 'stage' => 'lead']);

        $this->storage->method('load')->with('1')->willReturn($lead);

        // lead cannot go directly to 'won'
        $result = $this->action->handle('lead', ['id' => '1', 'stage' => 'won']);

        self::assertFalse($result->ok);
        self::assertSame(422, $result->error['status']);
        self::assertStringContainsString('Cannot transition', $result->error['detail']);
    }

    #[Test]
    public function handle_returns_error_when_business_rules_fail(): void
    {
        // proposal requires contact_email
        $lead = new Lead(['id' => 1, 'label' => 'Test Lead', 'stage' => 'contacted']);

        $this->storage->method('load')->with('1')->willReturn($lead);

        $result = $this->action->handle('lead', ['id' => '1', 'stage' => 'proposal']);

        self::assertFalse($result->ok);
        self::assertSame(422, $result->error['status']);
        self::assertStringContainsString('Contact email', $result->error['detail'] ?? $result->error['title']);
    }

    #[Test]
    public function handle_succeeds_for_valid_transition(): void
    {
        $lead = new Lead(['id' => 1, 'label' => 'Test Lead', 'stage' => 'lead']);

        $this->storage->method('load')->with('1')->willReturn($lead);
        $this->storage->expects(self::once())->method('save');

        $result = $this->action->handle('lead', ['id' => '1', 'stage' => 'qualified']);

        self::assertTrue($result->ok);
        self::assertSame('lead', $result->data['type']);
        self::assertSame('qualified', $result->data['attributes']['stage']);
        self::assertNotEmpty($result->data['attributes']['stage_changed_at']);
    }

    #[Test]
    public function handle_succeeds_for_proposal_with_contact_email(): void
    {
        $lead = new Lead([
            'id' => 1,
            'label' => 'Test Lead',
            'stage' => 'contacted',
            'contact_email' => 'test@example.com',
        ]);

        $this->storage->method('load')->with('1')->willReturn($lead);
        $this->storage->expects(self::once())->method('save');

        $result = $this->action->handle('lead', ['id' => '1', 'stage' => 'proposal']);

        self::assertTrue($result->ok);
        self::assertSame('proposal', $result->data['attributes']['stage']);
    }
}
