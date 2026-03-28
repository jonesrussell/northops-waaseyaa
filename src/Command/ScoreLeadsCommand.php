<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pipeline\ProspectScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[AsCommand(
    name: 'northops:leads:score',
    description: 'Batch-score unscored leads for organizational fit and brand routing',
)]
final class ScoreLeadsCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ProspectScoringService $scoringService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max leads to score', '50')
            ->addOption('rescore', null, InputOption::VALUE_NONE, 'Re-score already scored leads');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        if ($limit < 1) {
            $output->writeln('<error>Limit must be at least 1.</error>');
            return Command::FAILURE;
        }

        $rescore = (bool) $input->getOption('rescore');
        $storage = $this->entityTypeManager->getStorage('lead');

        $query = $storage->getQuery();
        $query->range(0, $limit);
        $ids = $query->execute();

        if (empty($ids)) {
            $output->writeln('<comment>No leads found.</comment>');
            return Command::SUCCESS;
        }

        $leads = $storage->loadMultiple($ids);

        $rows = [];
        $scoredCount = 0;

        foreach ($leads as $lead) {
            $existingScore = $lead->get('score');

            if ($existingScore !== null && $existingScore !== '' && !$rescore) {
                continue;
            }

            $input_data = [
                'sector'         => $lead->get('sector') ?: null,
                'qualify_rating' => ($lead->get('qualify_rating') !== null && $lead->get('qualify_rating') !== '')
                    ? (int) $lead->get('qualify_rating')
                    : null,
                'value'          => ($lead->get('value') !== null && $lead->get('value') !== '')
                    ? (float) $lead->get('value')
                    : null,
                'closing_date'   => $lead->get('closing_date') ?: null,
                'signal_type'    => $lead->get('signal_type') ?: null,
                'org_type'       => $lead->get('org_type') ?: null,
                'title'          => $lead->get('label') ?: null,
                'description'    => $lead->get('description') ?: null,
            ];

            $result = $this->scoringService->score($input_data);

            $lead->set('score', $result['score']);
            $lead->set('recommended_brand', $result['recommended_brand']);
            $storage->save($lead);

            $breakdown = $result['breakdown'];

            $rows[] = [
                $lead->get('label') ?? '(no name)',
                $result['score'],
                $result['recommended_brand'],
                $breakdown['sector'],
                $breakdown['qualification'],
                $breakdown['value'],
                $breakdown['signal'],
            ];

            ++$scoredCount;
        }

        if ($scoredCount === 0) {
            $output->writeln('<comment>All leads already scored. Use --rescore to re-score.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Score', 'Brand', 'Sector Fit', 'Qualification', 'Value', 'Signal Bonus']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $output->writeln("<info>Scored {$scoredCount} lead(s).</info>");

        return Command::SUCCESS;
    }
}
