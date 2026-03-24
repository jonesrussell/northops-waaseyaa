<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

final class StageTransitionRules
{
    public const STAGES = ['lead', 'qualified', 'contacted', 'proposal', 'negotiation', 'won', 'lost'];

    public const SOURCES = ['inbound', 'rfp', 'referral', 'cold_outreach', 'partner', 'manual', 'other'];

    private const TRANSITIONS = [
        'lead' => ['qualified', 'lost'],
        'qualified' => ['contacted', 'lost'],
        'contacted' => ['proposal', 'lost'],
        'proposal' => ['negotiation', 'lost'],
        'negotiation' => ['won', 'lost'],
        'won' => [],
        'lost' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Returns validation errors for a stage transition, empty array if valid.
     *
     * @param array<string, mixed> $leadData
     * @return string[]
     */
    public static function validateTransition(string $from, string $to, array $leadData): array
    {
        $errors = [];

        if (!self::canTransition($from, $to)) {
            $errors[] = "Cannot transition from '{$from}' to '{$to}'.";
        }

        if ($to === 'proposal' && empty($leadData['contact_email'])) {
            $errors[] = "Contact email is required to move to 'proposal' stage.";
        }

        if ($to === 'won' && empty($leadData['value'])) {
            $errors[] = "Deal value is required to move to 'won' stage.";
        }

        return $errors;
    }

    public static function isValidStage(string $stage): bool
    {
        return in_array($stage, self::STAGES, true);
    }

    public static function isValidSource(string $source): bool
    {
        return in_array($source, self::SOURCES, true);
    }
}
