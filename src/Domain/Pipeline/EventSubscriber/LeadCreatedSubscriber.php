<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Entity\Lead;
use App\Entity\LeadActivity;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadCreatedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly string $discordWebhookUrl,
    ) {}

    public function handle(Lead $lead): void
    {
        $this->recordActivity($lead);
        $this->notifyDiscord($lead);
    }

    private function recordActivity(Lead $lead): void
    {
        $activity = new LeadActivity([
            'lead_id' => $lead->get('id'),
            'user_id' => 'system',
            'action' => 'created',
            'payload' => json_encode([
                'source' => $lead->getSource(),
                'stage' => $lead->getStage(),
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->entityTypeManager->getStorage('lead_activity')->save($activity);
    }

    private function notifyDiscord(Lead $lead): void
    {
        if ($this->discordWebhookUrl === '') {
            return;
        }

        $embed = [
            'title' => 'New Lead Created',
            'color' => 0x57F287,
            'fields' => [
                ['name' => 'Label', 'value' => mb_substr($lead->getLabel(), 0, 1024) ?: '(none)', 'inline' => false],
                ['name' => 'Source', 'value' => $lead->getSource() ?: '(none)', 'inline' => true],
                ['name' => 'Stage', 'value' => $lead->getStage(), 'inline' => true],
                ['name' => 'Contact', 'value' => $lead->getContactName() ?: '(none)', 'inline' => true],
                ['name' => 'Email', 'value' => $lead->getContactEmail() ?: '(none)', 'inline' => true],
            ],
            'timestamp' => date('c'),
        ];

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
