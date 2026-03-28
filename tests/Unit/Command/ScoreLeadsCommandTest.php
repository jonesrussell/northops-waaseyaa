<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ScoreLeadsCommand;
use App\Domain\Pipeline\ProspectScoringService;
use App\Entity\Lead;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class ScoreLeadsCommandTest extends TestCase
{
    private function makeStorageMock(array $leads): EntityStorageInterface
    {
        // Build a fluent query stub that returns IDs
        $ids = array_keys($leads);
        $query = new class($ids) implements EntityQueryInterface {
            public function __construct(private readonly array $ids) {}
            public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
            public function exists(string $field): static { return $this; }
            public function notExists(string $field): static { return $this; }
            public function sort(string $field, string $direction = 'ASC'): static { return $this; }
            public function range(int $offset, int $limit): static { return $this; }
            public function count(): static { return $this; }
            public function accessCheck(bool $check = true): static { return $this; }
            public function execute(): array { return $this->ids; }
        };

        return new class($leads, $query) implements EntityStorageInterface {
            public function __construct(
                private array $leads,
                private EntityQueryInterface $query,
            ) {}

            public function create(array $values = []): EntityInterface
            {
                throw new \LogicException('Not implemented in test stub.');
            }

            public function load(int|string $id): ?EntityInterface { return $this->leads[$id] ?? null; }

            public function loadMultiple(array $ids = []): array
            {
                return array_filter($this->leads, fn ($lead, $id) => in_array($id, $ids, true), ARRAY_FILTER_USE_BOTH);
            }

            public function save(EntityInterface $entity): int { return 2; }

            public function delete(array $entities): void {}

            public function getQuery(): EntityQueryInterface { return $this->query; }

            public function getEntityTypeId(): string { return 'lead'; }
        };
    }

    public function testScoresUnscoredLeads(): void
    {
        $lead = new Lead([
            'id'             => 1,
            'label'          => 'Acme Corp',
            'sector'         => 'IT',
            'qualify_rating' => 8,
            'value'          => 60_000.0,
        ]);

        $storage = $this->makeStorageMock([1 => $lead]);

        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $entityTypeManager->method('getStorage')->with('lead')->willReturn($storage);

        $scoringService = new ProspectScoringService();

        $command = new ScoreLeadsCommand($entityTypeManager, $scoringService);

        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Acme Corp', $output);
        $this->assertStringContainsString('Scored 1 lead(s)', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testSkipsAlreadyScoredLeadsWithoutRescore(): void
    {
        $lead = new Lead([
            'id'    => 1,
            'label' => 'Already Scored',
            'score' => 75,
        ]);

        $storage = $this->makeStorageMock([1 => $lead]);

        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $entityTypeManager->method('getStorage')->with('lead')->willReturn($storage);

        $scoringService = new ProspectScoringService();

        $command = new ScoreLeadsCommand($entityTypeManager, $scoringService);

        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('already scored', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testRescoreFlagOverridesSkip(): void
    {
        $lead = new Lead([
            'id'    => 1,
            'label' => 'Rescore Me',
            'score' => 10,
        ]);

        $storage = $this->makeStorageMock([1 => $lead]);

        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $entityTypeManager->method('getStorage')->with('lead')->willReturn($storage);

        $scoringService = new ProspectScoringService();

        $command = new ScoreLeadsCommand($entityTypeManager, $scoringService);

        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute(['--rescore' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Scored 1 lead(s)', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testInvalidLimitReturnsFailure(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $scoringService = new ProspectScoringService();

        $command = new ScoreLeadsCommand($entityTypeManager, $scoringService);

        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute(['--limit' => '0']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testNoLeadsReturnsSuccess(): void
    {
        $storage = $this->makeStorageMock([]);

        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $entityTypeManager->method('getStorage')->with('lead')->willReturn($storage);

        $scoringService = new ProspectScoringService();

        $command = new ScoreLeadsCommand($entityTypeManager, $scoringService);

        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('No leads found', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }
}
