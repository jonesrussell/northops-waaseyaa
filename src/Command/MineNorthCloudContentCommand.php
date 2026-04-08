<?php

declare(strict_types=1);

namespace App\Command;

use Predis\Client as PredisClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Long-running subscriber that listens to the North Cloud coforge:core Redis
 * channel and creates content-queue issues in jonesrussell/jonesrussell for
 * qualifying developer/AI/software articles.
 *
 * Run as a daemon (systemd or screen):
 *   php bin/waaseyaa northcloud:mine-content
 *
 * Required env vars:
 *   NORTHCLOUD_REDIS_URL   Redis DSN for North Cloud (e.g. redis://127.0.0.1:6379)
 *   CONTENT_GITHUB_REPO    Target repo for issues (default: jonesrussell/jonesrussell)
 */
#[AsCommand(
    name: 'northcloud:mine-content',
    description: 'Subscribe to North Cloud Redis and surface qualifying articles as content-queue issues',
)]
final class MineNorthCloudContentCommand extends Command
{
    private const CHANNEL = 'coforge:core';
    private const MIN_QUALITY = 65;
    private const ALLOWED_TYPES = ['article', 'blog_post'];
    private const LABELS = 'content-queue,stage:mined,source:north-cloud,type:text-post';
    private const DEFAULT_REPO = 'jonesrussell/jonesrussell';

    /** In-process dedup: content IDs seen since the process started. */
    private array $seen = [];

    protected function configure(): void
    {
        $this
            ->addOption(
                'redis-url',
                null,
                InputOption::VALUE_REQUIRED,
                'North Cloud Redis DSN',
                getenv('NORTHCLOUD_REDIS_URL') ?: 'redis://127.0.0.1:6379',
            )
            ->addOption(
                'github-repo',
                null,
                InputOption::VALUE_REQUIRED,
                'GitHub repo to create content-queue issues in',
                getenv('CONTENT_GITHUB_REPO') ?: self::DEFAULT_REPO,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Log qualifying items without creating GitHub issues',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $redisUrl = (string) $input->getOption('redis-url');
        $repo = (string) $input->getOption('github-repo');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN — no issues will be created.</comment>');
        }

        $output->writeln(sprintf(
            '<info>Connecting to North Cloud Redis and subscribing to %s...</info>',
            self::CHANNEL,
        ));

        $client = new PredisClient($redisUrl);
        $pubsub = $client->pubSubLoop();
        $pubsub->subscribe(self::CHANNEL);

        foreach ($pubsub as $message) {
            if ($message->kind !== 'message') {
                continue;
            }

            $item = json_decode((string) $message->payload, true);
            if (!is_array($item) || json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln('<error>Invalid JSON payload — skipping.</error>');
                continue;
            }

            $id = $item['id'] ?? null;
            if ($id === null) {
                continue;
            }

            if (isset($this->seen[$id])) {
                $output->writeln("Duplicate skipped: {$id}");
                continue;
            }

            if (!$this->qualifies($item)) {
                $output->writeln(sprintf(
                    'Filtered out: quality=%d type=%s title="%s"',
                    $item['quality_score'] ?? 0,
                    $item['content_type'] ?? 'unknown',
                    mb_substr($item['title'] ?? '', 0, 60),
                ));
                continue;
            }

            $this->seen[$id] = true;

            $title = $item['title'] ?? 'Untitled';
            $output->writeln(sprintf('<info>Qualifying article:</info> %s', $title));

            if (!$dryRun) {
                $this->createIssue($item, $repo, $output);
            }
        }

        return Command::SUCCESS;
    }

    private function qualifies(array $item): bool
    {
        $qualityScore = $item['quality_score'] ?? 0;
        $contentType = $item['content_type'] ?? '';
        $contentSubtype = $item['content_subtype'] ?? '';

        if ($qualityScore < self::MIN_QUALITY) {
            return false;
        }

        if (!in_array($contentType, self::ALLOWED_TYPES, true)) {
            // Also allow articles with a blog_post subtype
            if ($contentType !== 'article' || $contentSubtype !== 'blog_post') {
                return false;
            }
        }

        return true;
    }

    private function createIssue(array $item, string $repo, OutputInterface $output): void
    {
        $title = sprintf('[content] %s', $item['title'] ?? 'Untitled article');
        $body = $this->buildBody($item);

        $process = new Process([
            'gh', 'issue', 'create',
            '--repo', $repo,
            '--title', $title,
            '--label', self::LABELS,
            '--body', $body,
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            $issueUrl = trim($process->getOutput());
            $output->writeln(sprintf('<info>Issue created:</info> %s', $issueUrl));
        } else {
            $output->writeln(sprintf(
                '<error>Failed to create issue:</error> %s',
                trim($process->getErrorOutput()),
            ));
        }
    }

    private function buildBody(array $item): string
    {
        $url = $item['canonical_url'] ?? $item['source'] ?? '';
        $domain = parse_url($url, PHP_URL_HOST) ?? 'unknown';
        $quality = $item['quality_score'] ?? 0;
        $topics = implode(', ', $item['topics'] ?? []);
        $publishedAt = $item['published_date'] ?? $item['publisher']['published_at'] ?? 'unknown';
        $intro = $item['intro'] ?? $item['og_description'] ?? $item['description'] ?? '';

        return <<<BODY
## Source
North Cloud signal: coforge:core | {$domain}

## Content Seed
{$item['title']}
{$url}
Published: {$publishedAt}
Quality: {$quality}
Topics: {$topics}

{$intro}

## Suggested Type
text-post

## Suggested Channels
x, linkedin, facebook

## Generated Artifacts

BODY;
    }
}
