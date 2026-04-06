<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pipeline\EventSubscriber\LeadQualifiedSubscriber;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Qualification\QualificationService;
use App\Entity\Lead;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[AsCommand(
    name: 'pipeline:qualify-leads',
    description: 'Batch-qualify unqualified leads via the Anthropic API',
)]
final class QualifyLeadsCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly QualificationService $qualificationService,
        private readonly LeadManager $leadManager,
        private readonly LeadQualifiedSubscriber $qualifiedSubscriber,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max leads to qualify', '50');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be qualified without calling the API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        $storage = $this->entityTypeManager->getStorage('lead');
        $allIds = $storage->getQuery()->execute();

        // Filter to leads missing qualify_rating (entity query can't match absent JSON fields).
        $unqualifiedLeads = [];
        foreach ($allIds as $id) {
            $lead = $storage->load((int) $id);
            if ($lead instanceof Lead && ($lead->get('qualify_rating') === null || $lead->get('qualify_rating') === '')) {
                $unqualifiedLeads[] = $lead;
            }
        }

        $total = count($unqualifiedLeads);
        $output->writeln(sprintf('Found %d unqualified leads (processing up to %d)', $total, $limit));

        if ($dryRun) {
            foreach (array_slice($unqualifiedLeads, 0, $limit) as $lead) {
                $output->writeln(sprintf('  [%d] %s', $lead->id(), $lead->getLabel()));
            }
            return Command::SUCCESS;
        }

        $qualified = 0;
        $errors = 0;

        foreach (array_slice($unqualifiedLeads, 0, $limit) as $lead) {
            try {
                $result = $this->qualificationService->qualify($lead);

                $updateData = [
                    'qualify_rating' => $result['rating'],
                    'qualify_confidence' => $result['confidence'],
                    'qualify_keywords' => json_encode($result['keywords'], JSON_THROW_ON_ERROR),
                    'qualify_notes' => $result['summary'] ?? '',
                    'qualify_raw' => $result['raw'],
                    'sector' => $result['sector'] ?? $lead->getSector(),
                    'score' => $result['score'],
                    'recommended_brand' => $result['recommended_brand'],
                ];

                $brandId = $this->resolveBrandId($result['recommended_brand']);
                if ($brandId !== null) {
                    $updateData['brand_id'] = $brandId;
                }

                $this->leadManager->update($lead, $updateData);

                $this->qualifiedSubscriber->handle($lead, $result);
                $qualified++;

                $output->writeln(sprintf(
                    '  [%d] %s -> rating=%d score=%d brand=%s',
                    $lead->id(),
                    $lead->getLabel(),
                    $result['rating'],
                    $result['score'],
                    $result['recommended_brand'],
                ));
            } catch (\Throwable $e) {
                $errors++;
                $output->writeln(sprintf('  [%d] ERROR: %s', $lead->id(), $e->getMessage()));
            }
        }

        $output->writeln(sprintf("\nDone: %d qualified, %d errors, %d remaining", $qualified, $errors, $total - $qualified - $errors));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function resolveBrandId(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        $ids = $this->entityTypeManager->getStorage('brand')
            ->getQuery()
            ->condition('slug', $slug)
            ->execute();

        return $ids !== [] ? (int) reset($ids) : null;
    }
}
