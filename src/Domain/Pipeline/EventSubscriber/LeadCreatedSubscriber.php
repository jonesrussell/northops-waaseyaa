<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Entity\Lead;

final class LeadCreatedSubscriber
{
    public function __construct(
        private readonly string $discordWebhookUrl,
    ) {}

    public function handle(Lead $lead): void
    {
        $this->notifyDiscord($lead);
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
