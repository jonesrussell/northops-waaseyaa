<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Entity\Lead;
use App\Entity\LeadActivity;
use App\Support\DiscordNotifier;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadQualifiedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DiscordNotifier $discordNotifier,
    ) {}

    /**
     * @param array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string} $qualificationResult
     */
    public function handle(Lead $lead, array $qualificationResult): void
    {
        $this->recordActivity($lead, $qualificationResult);
        $this->notifyDiscord($lead, $qualificationResult);
    }

    /**
     * @param array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string} $qualificationResult
     */
    private function recordActivity(Lead $lead, array $qualificationResult): void
    {
        $activity = new LeadActivity([
            'lead_id' => $lead->get('id'),
            'user_id' => 'system',
            'action' => 'qualification',
            'payload' => json_encode([
                'rating' => $qualificationResult['rating'],
                'sector' => $qualificationResult['sector'],
                'confidence' => $qualificationResult['confidence'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->entityTypeManager->getStorage('lead_activity')->save($activity);
    }

    /**
     * @param array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string} $qualificationResult
     */
    private function notifyDiscord(Lead $lead, array $qualificationResult): void
    {
        $rating = $qualificationResult['rating'];
        $color = match (true) {
            $rating >= 70 => 0x57F287,  // Green — strong lead
            $rating >= 40 => 0xFEE75C,  // Yellow — moderate
            default => 0xED4245,         // Red — weak
        };

        $keywords = implode(', ', $qualificationResult['keywords']);

        $this->discordNotifier->sendEmbed([
            'title' => 'Lead Qualified',
            'color' => $color,
            'fields' => [
                ['name' => 'Lead', 'value' => mb_substr($lead->getLabel(), 0, 1024) ?: '(none)', 'inline' => false],
                ['name' => 'Rating', 'value' => (string) $rating . '/100', 'inline' => true],
                ['name' => 'Confidence', 'value' => number_format($qualificationResult['confidence'], 2), 'inline' => true],
                ['name' => 'Sector', 'value' => $qualificationResult['sector'] ?? '(none)', 'inline' => true],
                ['name' => 'Keywords', 'value' => $keywords ?: '(none)', 'inline' => false],
                ['name' => 'Summary', 'value' => mb_substr($qualificationResult['summary'] ?? '', 0, 1024) ?: '(none)', 'inline' => false],
            ],
            'timestamp' => date('c'),
        ]);
    }
}
