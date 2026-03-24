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

        return null;
    }
}
