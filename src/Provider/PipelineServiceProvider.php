<?php

declare(strict_types=1);

namespace App\Provider;

use App\Command\ImportRfpsCommand;
use App\Command\SeedBrandsCommand;
use App\Domain\Pipeline\EventSubscriber\LeadCreatedSubscriber;
use App\Domain\Pipeline\EventSubscriber\StageChangedSubscriber;
use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Pipeline\RfpImportService;
use App\Support\DiscordNotifier;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\HttpClient\StreamHttpClient;
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
        $httpClient = new StreamHttpClient();
        $discordNotifier = new DiscordNotifier(
            new StreamHttpClient(timeout: 5.0),
            $this->config['discord']['webhook_url'] ?? '',
        );
        $northcloudUrl = $this->config['pipeline']['northcloud_url'] ?? '';

        $leadCreatedSubscriber = new LeadCreatedSubscriber($entityTypeManager, $discordNotifier);
        $stageChangedSubscriber = new StageChangedSubscriber($entityTypeManager, $discordNotifier);
        $leadManager = new LeadManager($entityTypeManager, $leadCreatedSubscriber, $stageChangedSubscriber);
        $leadFactory = new LeadFactory($leadManager, $entityTypeManager);
        $rfpImportService = new RfpImportService($leadFactory, $httpClient, $northcloudUrl);

        $defaultBrandId = $this->resolveDefaultBrandId($entityTypeManager);

        return [
            new SeedBrandsCommand($entityTypeManager),
            new ImportRfpsCommand($rfpImportService, $defaultBrandId),
        ];
    }

    private function resolveDefaultBrandId(EntityTypeManager $etm): int
    {
        $defaultSlug = $this->config['pipeline']['default_brand'] ?? 'northops';

        try {
            $ids = $etm->getStorage('brand')->getQuery()
                ->condition('slug', $defaultSlug)
                ->execute();

            if ($ids !== []) {
                return (int) reset($ids);
            }
        } catch (\Throwable) {
            // Brand table may not exist yet
        }

        return 1;
    }
}
