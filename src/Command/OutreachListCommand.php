<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pipeline\OutreachTemplateRenderer;
use App\Entity\Brand;
use App\Entity\Lead;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[AsCommand(
    name: 'northops:outreach:list',
    description: 'List leads needing outreach, with optional email preview',
)]
final class OutreachListCommand extends Command
{
    /** Maps Lead source values to OutreachTemplateRenderer template names. */
    private const SIGNAL_TEMPLATE_MAP = [
        'outdated_website' => 'outdated_website',
        'funding_win'      => 'funding_win',
        'job_posting'      => 'job_posting',
        'new_program'      => 'new_program',
    ];

    private const DEFAULT_TEMPLATE = 'outdated_website';

    private const SENDER_NAME = 'Russell Jones';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly OutreachTemplateRenderer $outreachTemplateRenderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('brand', 'b', InputOption::VALUE_REQUIRED, 'Filter by brand slug (webnet or northops)')
            ->addOption('preview', 'p', InputOption::VALUE_REQUIRED, 'Lead UUID to preview outreach email for')
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'Minimum lead score (qualify_rating)', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $previewUuid = $input->getOption('preview');

        if ($previewUuid !== null) {
            return $this->previewEmail((string) $previewUuid, $output);
        }

        return $this->listLeads($input, $output);
    }

    private function listLeads(InputInterface $input, OutputInterface $output): int
    {
        $minScore = (int) $input->getOption('min-score');
        $brandSlug = $input->getOption('brand');

        $leadStorage = $this->entityTypeManager->getStorage('lead');

        // Build lead query: stage = qualified, score >= minScore
        $query = $leadStorage->getQuery();
        $query->condition('stage', 'qualified');
        $query->condition('qualify_rating', $minScore, '>=');
        $query->sort('qualify_rating', 'DESC');

        $ids = $query->execute();

        if (empty($ids)) {
            $output->writeln('<comment>No qualified leads found.</comment>');
            return Command::SUCCESS;
        }

        /** @var Lead[] $leads */
        $leads = $leadStorage->loadMultiple($ids);

        // Collect UUIDs of leads that already have outreach records
        $outreachStorage = $this->entityTypeManager->getStorage('outreach');
        $outreachQuery = $outreachStorage->getQuery();
        $existingOutreachIds = $outreachQuery->execute();
        $leadsWithOutreach = [];

        if (!empty($existingOutreachIds)) {
            $outreachEntities = $outreachStorage->loadMultiple($existingOutreachIds);
            foreach ($outreachEntities as $outreach) {
                /** @var \App\Entity\Outreach $outreach */
                $leadsWithOutreach[$outreach->getLeadUuid()] = true;
            }
        }

        // Apply brand filter and outreach exclusion
        $filtered = [];
        foreach ($leads as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            // Exclude leads that already have outreach
            if (isset($leadsWithOutreach[$lead->uuid()])) {
                continue;
            }

            // Apply brand filter if given
            if ($brandSlug !== null) {
                $brand = $this->loadBrandForLead($lead);
                if ($brand === null || $brand->getSlug() !== (string) $brandSlug) {
                    continue;
                }
            }

            $filtered[] = $lead;
        }

        if (empty($filtered)) {
            $output->writeln('<comment>No leads require outreach with the given filters.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Score', 'Brand', 'Sector', 'Signal', 'Contact']);

        foreach ($filtered as $lead) {
            $brand = $this->loadBrandForLead($lead);
            $table->addRow([
                $lead->getLabel() ?: $lead->getCompanyName(),
                (string) $lead->getQualifyRating(),
                $brand !== null ? $brand->getSlug() : '—',
                $lead->getSector() ?: '—',
                $lead->getSource() ?: '—',
                $lead->getContactEmail() ?: $lead->getContactName() ?: '—',
            ]);
        }

        $table->render();
        $output->writeln(sprintf('<info>%d lead(s) ready for outreach.</info>', count($filtered)));

        return Command::SUCCESS;
    }

    private function previewEmail(string $leadUuid, OutputInterface $output): int
    {
        $leadStorage = $this->entityTypeManager->getStorage('lead');

        // Find lead by UUID
        $query = $leadStorage->getQuery();
        $query->condition('uuid', $leadUuid);
        $ids = $query->execute();

        if (empty($ids)) {
            $output->writeln(sprintf('<error>Lead with UUID "%s" not found.</error>', $leadUuid));
            return Command::FAILURE;
        }

        /** @var Lead|null $lead */
        $lead = $leadStorage->load((int) reset($ids));

        if (!$lead instanceof Lead) {
            $output->writeln('<error>Failed to load lead entity.</error>');
            return Command::FAILURE;
        }

        $brand = $this->loadBrandForLead($lead);
        $brandSlug = $brand !== null ? $brand->getSlug() : 'northops';

        // Map brand slug to renderer brand key
        $rendererBrand = $this->resolveRendererBrand($brandSlug);

        // Determine template from signal/source
        $signal = $lead->getSource();
        $templateName = self::SIGNAL_TEMPLATE_MAP[$signal] ?? self::DEFAULT_TEMPLATE;

        $variables = [
            'contact_name'      => $lead->getContactName() ?: 'there',
            'organization_name' => $lead->getCompanyName() ?: $lead->getLabel(),
            'signal_detail'     => $this->buildSignalDetail($signal, $lead),
            'sender_name'       => self::SENDER_NAME,
        ];

        try {
            $result = $this->outreachTemplateRenderer->render($templateName, $rendererBrand, $variables);
        } catch (\InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>Template render failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Subject:</info> %s', $result['subject']));
        $output->writeln(sprintf('<comment>Template:</comment> %s | <comment>Brand:</comment> %s', $result['template_used'], $result['brand']));
        $output->writeln('');
        $output->writeln($result['body']);

        return Command::SUCCESS;
    }

    private function loadBrandForLead(Lead $lead): ?Brand
    {
        $brandId = $lead->getBrandId();
        if ($brandId === 0) {
            return null;
        }

        $brandStorage = $this->entityTypeManager->getStorage('brand');
        $brand = $brandStorage->load($brandId);

        return $brand instanceof Brand ? $brand : null;
    }

    /**
     * Map brand slug to the renderer's known brand keys (webnet or northops).
     */
    private function resolveRendererBrand(string $slug): string
    {
        return match (true) {
            str_contains($slug, 'web') => 'webnet',
            default                    => 'northops',
        };
    }

    private function buildSignalDetail(string $signal, Lead $lead): string
    {
        $notes = $lead->getQualifyNotes();

        return match ($signal) {
            'outdated_website' => $notes ?: 'your website appears to be outdated',
            'funding_win'      => $notes ?: 'receiving recent funding',
            'job_posting'      => $notes ?: 'hiring web development talent',
            'new_program'      => $notes ?: 'launching a new program or initiative',
            default            => $notes ?: 'your organization\'s recent digital activity',
        };
    }
}
