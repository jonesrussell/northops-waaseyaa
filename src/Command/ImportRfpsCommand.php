<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pipeline\RfpImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pipeline:import-rfps',
    description: 'Import RFPs from north-cloud into the lead pipeline',
)]
final class ImportRfpsCommand extends Command
{
    public function __construct(
        private readonly RfpImportService $rfpImportService,
        private readonly int $defaultBrandId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Lookback window in days', '7')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported without persisting')
            ->addOption('auto-qualify', null, InputOption::VALUE_NONE, 'Run AI qualification on each imported lead');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        if ($days < 1 || $days > 365) {
            $output->writeln('<error>Days must be between 1 and 365.</error>');
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $autoQualify = (bool) $input->getOption('auto-qualify');

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN — no leads will be created.</comment>');
        }

        if ($autoQualify && !$dryRun) {
            $output->writeln('<info>Auto-qualification enabled — each imported lead will be scored via Claude API.</info>');
        }

        $output->writeln("Importing RFPs from the last {$days} days...");

        try {
            $stats = $this->rfpImportService->import($this->defaultBrandId, $days, $dryRun, $autoQualify);
        } catch (\Throwable $e) {
            $output->writeln("<error>Import failed: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln("  <info>Imported:</info> {$stats['imported']}");
        $output->writeln("  <comment>Skipped:</comment> {$stats['skipped']}");

        if ($autoQualify) {
            $output->writeln("  <info>Qualified:</info> {$stats['qualified']}");
        }

        if ($stats['errors'] > 0) {
            $output->writeln("  <error>Errors:</error> {$stats['errors']}");
        }

        $output->writeln('<info>Import complete.</info>');

        return Command::SUCCESS;
    }
}
