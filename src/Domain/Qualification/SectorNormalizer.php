<?php

declare(strict_types=1);

namespace App\Domain\Qualification;

final class SectorNormalizer
{
    public const SECTORS = [
        'IT', 'Networks', 'Security', 'Cloud', 'Telecom',
        'Software', 'Infrastructure', 'DevOps', 'AI', 'Other',
    ];

    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);

        foreach (self::SECTORS as $sector) {
            if (strtolower($sector) === strtolower($trimmed)) {
                return $sector;
            }
        }

        return 'Other';
    }

    /**
     * Detect organization type from sector/description text.
     * Priority: indigenous > nonprofit > charity > community > government > startup.
     */
    public static function detectOrganizationType(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $lower = strtolower(trim($text));

        // Indigenous (highest priority)
        if (self::containsAny($lower, ['indigenous', 'first nation', 'band council', 'metis', 'métis', 'inuit'])) {
            return 'indigenous';
        }

        // Non-profit
        if (self::containsAny($lower, ['non-profit', 'nonprofit', 'foundation', '501c3'])) {
            return 'non_profit';
        }

        // Charity
        if (self::containsAny($lower, ['charity', 'registered charity'])) {
            return 'charity';
        }

        // Community
        if (self::containsAny($lower, ['community', 'co-op', 'cooperative', 'association'])) {
            return 'community';
        }

        // Government
        if (self::containsAny($lower, ['government', 'municipal', 'public sector', 'crown'])) {
            return 'government';
        }

        // Startup
        if (self::containsAny($lower, ['startup', 'saas', 'tech', 'funded', 'series a', 'seed'])) {
            return 'startup';
        }

        return null;
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
