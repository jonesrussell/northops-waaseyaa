<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[AsCommand(
    name: 'pipeline:decay-scores',
    description: 'Reduce lead scores based on inactivity with brand-specific decay rates',
)]
final class DecayScoresCommand extends Command
{
    /** @var array<string, array<int, int>> brand => [days => penalty] */
    private const DECAY_RATES = [
        'northops' => [14 => 5, 30 => 10, 60 => 20],
        'webnet' => [30 => 3, 60 => 8, 90 => 15],
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without saving')
            ->addOption('brand', null, InputOption::VALUE_REQUIRED, 'Only decay leads for this brand slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $brandFilter = $input->getOption('brand');

        $storage = $this->entityTypeManager->getStorage('lead');
        $ids = $storage->getQuery()->execute();

        if ($ids === []) {
            $output->writeln('<comment>No leads found.</comment>');
            return Command::SUCCESS;
        }

        $leads = $storage->loadMultiple($ids);
        $brandMap = $this->buildBrandMap();
        $decayed = 0;

        foreach ($leads as $lead) {
            $score = (int) ($lead->get('qualify_rating') ?? 0);
            if ($score <= 0) {
                continue;
            }

            $brandId = (int) ($lead->get('brand_id') ?? 0);
            $brandSlug = $brandMap[$brandId] ?? 'northops';

            if ($brandFilter !== null && $brandSlug !== $brandFilter) {
                continue;
            }

            $lastScored = $lead->get('last_scored_at') ?? $lead->get('updated_at') ?? $lead->get('created_at') ?? '';
            if ($lastScored === '') {
                continue;
            }

            $daysSince = self::daysSince($lastScored);
            $penalty = self::calculateDecay($brandSlug, $daysSince);

            if ($penalty <= 0) {
                continue;
            }

            $newScore = max(0, $score - $penalty);
            $newTier = self::tierFromScore($newScore);

            if ($dryRun) {
                $output->writeln(sprintf(
                    '  %s: %d → %d (-%d, %dd inactive, %s)',
                    $lead->get('label') ?? '(unnamed)',
                    $score,
                    $newScore,
                    $penalty,
                    $daysSince,
                    $brandSlug,
                ));
            } else {
                $lead->set('qualify_rating', $newScore);
                $lead->set('tier', $newTier);
                $lead->set('last_scored_at', date('c'));
                $storage->save($lead);
            }

            $decayed++;
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $output->writeln("<info>{$prefix}Decayed {$decayed} lead(s).</info>");

        return Command::SUCCESS;
    }

    /**
     * Calculate the decay penalty for a brand and inactivity period.
     * Returns the LARGEST applicable penalty (not cumulative).
     */
    public static function calculateDecay(string $brandSlug, int $daysSince): int
    {
        $rates = self::DECAY_RATES[$brandSlug] ?? self::DECAY_RATES['northops'];
        $penalty = 0;

        foreach ($rates as $threshold => $amount) {
            if ($daysSince >= $threshold) {
                $penalty = max($penalty, $amount);
            }
        }

        return $penalty;
    }

    public static function tierFromScore(int $score): string
    {
        if ($score >= 80) {
            return 'T1';
        }
        if ($score >= 50) {
            return 'T2';
        }

        return 'T3';
    }

    public static function daysSince(string $dateString): int
    {
        try {
            $then = new \DateTimeImmutable($dateString);
            $now = new \DateTimeImmutable();

            return max(0, (int) $now->diff($then)->days);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int, string> brandId => slug */
    private function buildBrandMap(): array
    {
        $map = [];

        try {
            $brandIds = $this->entityTypeManager->getStorage('brand')->getQuery()->execute();
            $brands = $this->entityTypeManager->getStorage('brand')->loadMultiple($brandIds);

            foreach ($brands as $brand) {
                $map[(int) $brand->id()] = (string) ($brand->get('slug') ?? 'northops');
            }
        } catch (\Throwable) {
            // Brand table may not exist
        }

        return $map;
    }
}
