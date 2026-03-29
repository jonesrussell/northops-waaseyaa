<?php

declare(strict_types=1);

namespace Tests\Unit\Surface\Action;

use App\Domain\Pipeline\StageTransitionRules;
use App\Domain\Qualification\SectorNormalizer;
use App\Surface\Action\LeadBoardConfigAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeadBoardConfigAction::class)]
final class LeadBoardConfigActionTest extends TestCase
{
    #[Test]
    public function handle_returns_stages_transitions_sources_and_sectors(): void
    {
        $action = new LeadBoardConfigAction();

        $result = $action->handle('lead', []);

        self::assertTrue($result->ok);
        self::assertIsArray($result->data);

        self::assertSame(StageTransitionRules::STAGES, $result->data['stages']);
        self::assertSame(StageTransitionRules::getTransitions(), $result->data['transitions']);
        self::assertSame(StageTransitionRules::SOURCES, $result->data['sources']);
        self::assertSame(SectorNormalizer::SECTORS, $result->data['sectors']);
    }

    #[Test]
    public function handle_returns_expected_stage_list(): void
    {
        $action = new LeadBoardConfigAction();

        $result = $action->handle('lead', []);

        $stages = $result->data['stages'];
        self::assertContains('lead', $stages);
        self::assertContains('qualified', $stages);
        self::assertContains('contacted', $stages);
        self::assertContains('proposal', $stages);
        self::assertContains('negotiation', $stages);
        self::assertContains('won', $stages);
        self::assertContains('lost', $stages);
    }

    #[Test]
    public function handle_returns_valid_transitions_map(): void
    {
        $action = new LeadBoardConfigAction();

        $result = $action->handle('lead', []);

        $transitions = $result->data['transitions'];
        self::assertIsArray($transitions);

        // lead can go to qualified or lost
        self::assertContains('qualified', $transitions['lead']);
        self::assertContains('lost', $transitions['lead']);

        // won and lost are terminal
        self::assertSame([], $transitions['won']);
        self::assertSame([], $transitions['lost']);
    }
}
