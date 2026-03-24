<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Registers pipeline domain services into the DI container.
 *
 * Currently a placeholder — domain services are instantiated via
 * controller lazy-loaders in AppServiceProvider.
 */
final class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void {}
}
