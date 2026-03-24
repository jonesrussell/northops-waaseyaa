<?php

declare(strict_types=1);

namespace App\Provider;

use App\Command\SeedBrandsCommand;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Registers pipeline CLI commands and domain services.
 */
final class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void {}

    public function commands(
        EntityTypeManager $entityTypeManager,
        DatabaseInterface $database,
        EventDispatcherInterface $dispatcher,
    ): array {
        return [
            new SeedBrandsCommand($entityTypeManager),
        ];
    }
}
