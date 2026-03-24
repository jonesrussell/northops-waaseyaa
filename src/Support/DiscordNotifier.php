<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpRequestException;

final class DiscordNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $webhookUrl,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function notifyContactSubmission(string $name, string $email, string $message): void
    {
        $this->sendEmbed([
            'title' => 'New Contact Form Submission',
            'color' => 0x5865F2,
            'fields' => [
                ['name' => 'Name', 'value' => $name, 'inline' => true],
                ['name' => 'Email', 'value' => $email, 'inline' => true],
                ['name' => 'Message', 'value' => mb_substr($message, 0, 1024)],
            ],
            'timestamp' => date('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $embed
     */
    public function sendEmbed(array $embed): void
    {
        if ($this->webhookUrl === '') {
            return;
        }

        try {
            $this->httpClient->post($this->webhookUrl, [], ['embeds' => [$embed]]);
        } catch (HttpRequestException $e) {
            $this->logger->warning('Discord webhook notification failed', [
                'url' => preg_replace('/\/[\w-]+$/', '/***', $this->webhookUrl),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
