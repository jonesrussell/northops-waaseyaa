<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Entity\Lead;
use App\Entity\LeadActivity;
use Waaseyaa\Entity\EntityTypeManager;

final class StageChangedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly string $discordWebhookUrl,
    ) {}

    public function handle(Lead $lead, string $fromStage, string $toStage, ?string $userId = null): void
    {
        $this->recordActivity($lead, $fromStage, $toStage, $userId);

        if (in_array($toStage, ['qualified', 'won', 'lost'], true)) {
            $this->notifyDiscord($lead, $fromStage, $toStage);
        }
    }

    private function recordActivity(Lead $lead, string $fromStage, string $toStage, ?string $userId): void
    {
        $activity = new LeadActivity([
            'lead_id' => $lead->get('id'),
            'user_id' => $userId ?? 'system',
            'action' => 'stage_change',
            'payload' => json_encode(['from' => $fromStage, 'to' => $toStage], JSON_THROW_ON_ERROR),
        ]);

        $this->entityTypeManager->getStorage('lead_activity')->save($activity);
    }

    private function notifyDiscord(Lead $lead, string $fromStage, string $toStage): void
    {
        if ($this->discordWebhookUrl === '') {
            return;
        }

        $color = match ($toStage) {
            'won' => 0x57F287,       // Green
            'lost' => 0xED4245,      // Red
            'qualified' => 0x5865F2, // Blurple
            default => 0x99AAB5,     // Grey
        };

        $emoji = match ($toStage) {
            'won' => '🏆',
            'lost' => '❌',
            'qualified' => '✅',
            default => '➡️',
        };

        $embed = [
            'title' => "{$emoji} Stage Changed: {$fromStage} → {$toStage}",
            'color' => $color,
            'fields' => [
                ['name' => 'Lead', 'value' => mb_substr($lead->getLabel(), 0, 1024) ?: '(none)', 'inline' => false],
                ['name' => 'From', 'value' => $fromStage, 'inline' => true],
                ['name' => 'To', 'value' => $toStage, 'inline' => true],
                ['name' => 'Contact', 'value' => $lead->getContactName() ?: '(none)', 'inline' => true],
            ],
            'timestamp' => date('c'),
        ];

        if ($toStage === 'won' && $lead->getValue() !== '0') {
            $embed['fields'][] = ['name' => 'Deal Value', 'value' => '$' . $lead->getValue(), 'inline' => true];
        }

        $payload = json_encode(['embeds' => [$embed]], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($this->discordWebhookUrl, false, $context);
    }
}
