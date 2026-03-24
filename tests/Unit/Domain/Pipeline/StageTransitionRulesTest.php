<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\StageTransitionRules;
use PHPUnit\Framework\TestCase;

final class StageTransitionRulesTest extends TestCase
{
    public function testValidForwardTransitions(): void
    {
        $this->assertTrue(StageTransitionRules::canTransition('lead', 'qualified'));
        $this->assertTrue(StageTransitionRules::canTransition('qualified', 'contacted'));
        $this->assertTrue(StageTransitionRules::canTransition('contacted', 'proposal'));
        $this->assertTrue(StageTransitionRules::canTransition('proposal', 'negotiation'));
        $this->assertTrue(StageTransitionRules::canTransition('negotiation', 'won'));
    }

    public function testAnyStageCanTransitionToLost(): void
    {
        foreach (['lead', 'qualified', 'contacted', 'proposal', 'negotiation'] as $stage) {
            $this->assertTrue(
                StageTransitionRules::canTransition($stage, 'lost'),
                "Expected '{$stage}' → 'lost' to be allowed",
            );
        }
    }

    public function testCannotSkipStages(): void
    {
        $this->assertFalse(StageTransitionRules::canTransition('lead', 'proposal'));
        $this->assertFalse(StageTransitionRules::canTransition('lead', 'won'));
        $this->assertFalse(StageTransitionRules::canTransition('qualified', 'negotiation'));
    }

    public function testCannotTransitionFromTerminalStages(): void
    {
        $this->assertFalse(StageTransitionRules::canTransition('won', 'lost'));
        $this->assertFalse(StageTransitionRules::canTransition('lost', 'lead'));
        $this->assertFalse(StageTransitionRules::canTransition('won', 'negotiation'));
    }

    public function testProposalRequiresContactEmail(): void
    {
        $errors = StageTransitionRules::validateTransition('contacted', 'proposal', [
            'contact_email' => '',
            'value' => '10000',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('email', $errors[0]);
    }

    public function testProposalAllowedWithEmail(): void
    {
        $errors = StageTransitionRules::validateTransition('contacted', 'proposal', [
            'contact_email' => 'test@example.com',
            'value' => '',
        ]);

        $this->assertEmpty($errors);
    }

    public function testWonRequiresValue(): void
    {
        $errors = StageTransitionRules::validateTransition('negotiation', 'won', [
            'contact_email' => 'test@example.com',
            'value' => '',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('value', $errors[0]);
    }

    public function testWonAllowedWithValue(): void
    {
        $errors = StageTransitionRules::validateTransition('negotiation', 'won', [
            'contact_email' => 'test@example.com',
            'value' => '50000',
        ]);

        $this->assertEmpty($errors);
    }

    public function testIsValidStage(): void
    {
        $this->assertTrue(StageTransitionRules::isValidStage('lead'));
        $this->assertTrue(StageTransitionRules::isValidStage('won'));
        $this->assertFalse(StageTransitionRules::isValidStage('invalid'));
        $this->assertFalse(StageTransitionRules::isValidStage(''));
    }

    public function testIsValidSource(): void
    {
        $this->assertTrue(StageTransitionRules::isValidSource('inbound'));
        $this->assertTrue(StageTransitionRules::isValidSource('rfp'));
        $this->assertFalse(StageTransitionRules::isValidSource('unknown'));
        $this->assertFalse(StageTransitionRules::isValidSource(''));
    }
}
