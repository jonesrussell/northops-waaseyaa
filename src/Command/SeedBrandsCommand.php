<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Brand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[AsCommand(
    name: 'pipeline:seed-brands',
    description: 'Seed NorthOps and Web Networks brand entities (idempotent)',
)]
final class SeedBrandsCommand extends Command
{
    private const BRANDS = [
        [
            'name' => 'NorthOps',
            'slug' => 'northops',
            'primary_color' => '#1a1a2e',
            'tagline' => 'Senior engineering. Shipped in days.',
        ],
        [
            'name' => 'Web Networks',
            'slug' => 'web-networks',
            'primary_color' => '#2563eb',
            'tagline' => 'Digital co-operative.',
        ],
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storage = $this->entityTypeManager->getStorage('brand');

        foreach (self::BRANDS as $brandData) {
            $query = $storage->getQuery();
            $query->condition('slug', $brandData['slug']);
            $existing = $query->execute();

            if (!empty($existing)) {
                $output->writeln("  <comment>Skipped</comment> {$brandData['name']} (already exists)");
                continue;
            }

            $brand = new Brand($brandData);
            $storage->save($brand);
            $output->writeln("  <info>Created</info> {$brandData['name']}");
        }

        $output->writeln('<info>Brand seeding complete.</info>');

        return Command::SUCCESS;
    }
}
