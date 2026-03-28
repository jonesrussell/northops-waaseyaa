<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

final class ProspectScoringService
{
    private const ALLOWED_SECTORS = [
        'IT', 'Networks', 'Security', 'Cloud', 'Telecom',
        'Software', 'Infrastructure', 'DevOps', 'AI',
    ];

    private const WEBNET_ORG_TYPES = [
        'non_profit', 'government', 'public_institution', 'union', 'indigenous',
    ];

    private const WEBNET_KEYWORDS = [
        'non-profit', 'nonprofit', 'government', 'municipality',
        'first nation', 'indigenous', 'union', 'public institution',
        'school board', 'association',
    ];

    /**
     * Score a prospect for organizational fit and recommend a brand.
     *
     * @param array{
     *     sector?: ?string,
     *     qualify_rating?: ?int,
     *     value?: ?float,
     *     closing_date?: ?string,
     *     signal_type?: ?string,
     *     org_type?: ?string,
     *     title?: ?string,
     *     description?: ?string,
     * } $input
     *
     * @return array{score: int, breakdown: array{sector: int, qualification: int, value: int, urgency: int, signal: int}, recommended_brand: string}
     */
    public function score(array $input): array
    {
        $breakdown = [
            'sector' => $this->scoreSector($input['sector'] ?? null),
            'qualification' => $this->scoreQualification($input['qualify_rating'] ?? null),
            'value' => $this->scoreValue($input['value'] ?? null),
            'urgency' => $this->scoreUrgency($input['closing_date'] ?? null),
            'signal' => $this->scoreSignal($input['signal_type'] ?? null),
        ];

        return [
            'score' => (int) array_sum($breakdown),
            'breakdown' => $breakdown,
            'recommended_brand' => $this->routeBrand($input),
        ];
    }

    private function scoreSector(?string $sector): int
    {
        if ($sector === null || $sector === '') {
            return 5;
        }

        return in_array($sector, self::ALLOWED_SECTORS, true) ? 25 : 5;
    }

    private function scoreQualification(?int $rating): int
    {
        if ($rating === null) {
            return 0;
        }

        $clamped = max(0, min(10, $rating));

        return (int) round(($clamped / 10) * 25);
    }

    private function scoreValue(?float $value): int
    {
        if ($value === null) {
            return 2;
        }

        return match (true) {
            $value >= 100_000 => 20,
            $value >= 50_000 => 15,
            $value >= 20_000 => 10,
            $value >= 5_000 => 5,
            default => 2,
        };
    }

    private function scoreUrgency(?string $closingDate): int
    {
        if ($closingDate === null) {
            return 5;
        }

        $now = new \DateTimeImmutable('today');
        $deadline = new \DateTimeImmutable($closingDate);
        $days = (int) $now->diff($deadline)->format('%r%a');

        return match (true) {
            $days < 0 => 0,
            $days <= 7 => 5,
            $days <= 21 => 15,
            $days <= 45 => 10,
            default => 5,
        };
    }

    private function scoreSignal(?string $signalType): int
    {
        if ($signalType === null || $signalType === '') {
            return 0;
        }

        return match ($signalType) {
            'outdated_website' => 15,
            'funding_win' => 15,
            'job_posting' => 10,
            'new_program' => 10,
            'tech_migration' => 12,
            default => 0,
        };
    }

    private function routeBrand(array $input): string
    {
        $orgType = $input['org_type'] ?? null;

        if ($orgType !== null && $orgType !== '') {
            if (in_array($orgType, self::WEBNET_ORG_TYPES, true)) {
                return 'webnet';
            }

            if ($orgType === 'commercial') {
                return 'northops';
            }
        }

        // Text heuristic fallback
        $text = strtolower(
            ($input['title'] ?? '') . ' ' . ($input['description'] ?? '')
        );

        foreach (self::WEBNET_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return 'webnet';
            }
        }

        return 'northops';
    }
}
