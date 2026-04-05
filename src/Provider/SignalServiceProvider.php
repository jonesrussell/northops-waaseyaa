<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\Api\EnrichmentController;
use App\Controller\Api\SignalController;
use App\Domain\Enrichment\EnrichmentReceiver;
use App\Domain\Enrichment\EnrichmentService;
use App\Domain\Enrichment\Event\LeadEnrichedEvent;
use App\Domain\Pipeline\EventSubscriber\LeadEnrichedSubscriber;
use App\Domain\Pipeline\EventSubscriber\SignalIngestedSubscriber;
use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Pipeline\RoutingService;
use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Domain\Signal\SignalIngestionService;
use App\Domain\Signal\SignalMatcher;
use App\Support\DiscordNotifier;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\HttpClient\StreamHttpClient;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class SignalServiceProvider extends ServiceProvider
{
    private ?SignalController $signalController = null;
    private ?EnrichmentController $enrichmentController = null;
    private ?DiscordNotifier $discordNotifier = null;
    private ?SignalIngestionService $signalIngestionService = null;
    private ?EnrichmentService $enrichmentService = null;
    private ?EnrichmentReceiver $enrichmentReceiver = null;

    public function register(): void {}

    // ---------------------------------------------------------------
    // Shared service builders (lazy, cached)
    // ---------------------------------------------------------------

    private function getDiscordNotifier(): DiscordNotifier
    {
        return $this->discordNotifier ??= new DiscordNotifier(
            new StreamHttpClient(timeout: 5.0),
            $this->config['discord']['webhook_url'] ?? '',
        );
    }

    private function getSignalIngestionService(): SignalIngestionService
    {
        if ($this->signalIngestionService === null) {
            $etm = $this->resolve(EntityTypeManager::class);
            $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);

            if ($dispatcher instanceof EventDispatcherInterface) {
                $signalSubscriber = new SignalIngestedSubscriber($etm, $this->getDiscordNotifier());
                $dispatcher->addListener(SignalIngestedEvent::class, $signalSubscriber);
            }

            $leadManager = new LeadManager(
                $etm,
                new \App\Domain\Pipeline\EventSubscriber\LeadCreatedSubscriber($etm, $this->getDiscordNotifier()),
                new \App\Domain\Pipeline\EventSubscriber\StageChangedSubscriber($etm, $this->getDiscordNotifier()),
            );

            $leadFactory = new LeadFactory($leadManager, $etm, new RoutingService());

            $this->signalIngestionService = new SignalIngestionService(
                $etm,
                new SignalMatcher($etm),
                $leadFactory,
                $dispatcher,
                $this->config['pipeline']['signal_auto_create_threshold'] ?? 50,
            );
        }

        return $this->signalIngestionService;
    }

    private function getEnrichmentService(): EnrichmentService
    {
        return $this->enrichmentService ??= new EnrichmentService(
            $this->resolve(EntityTypeManager::class),
            new StreamHttpClient(),
            $this->config['pipeline']['northcloud_url'] ?? '',
            $this->config['pipeline']['api_key'] ?? '',
            $this->config['app']['url'] ?? '',
        );
    }

    private function getEnrichmentReceiver(): EnrichmentReceiver
    {
        if ($this->enrichmentReceiver === null) {
            $etm = $this->resolve(EntityTypeManager::class);
            $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);

            if ($dispatcher instanceof EventDispatcherInterface) {
                $enrichedSubscriber = new LeadEnrichedSubscriber($etm, $this->getDiscordNotifier());
                $dispatcher->addListener(LeadEnrichedEvent::class, $enrichedSubscriber);
            }

            $this->enrichmentReceiver = new EnrichmentReceiver($etm, $dispatcher);
        }

        return $this->enrichmentReceiver;
    }

    // ---------------------------------------------------------------
    // Controller builders
    // ---------------------------------------------------------------

    private function signalController(): SignalController
    {
        return $this->signalController ??= new SignalController(
            $this->getSignalIngestionService(),
            $this->resolve(EntityTypeManager::class),
            $this->config['pipeline']['api_key'] ?? '',
        );
    }

    private function enrichmentController(): EnrichmentController
    {
        return $this->enrichmentController ??= new EnrichmentController(
            $this->resolve(EntityTypeManager::class),
            $this->getEnrichmentService(),
            $this->getEnrichmentReceiver(),
            $this->config['pipeline']['api_key'] ?? '',
        );
    }

    // ---------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'api.signals.ingest',
            RouteBuilder::create('/api/signals')
                ->controller(fn () => $this->signalController()->ingest(Request::createFromGlobals()))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.signals.unmatched',
            RouteBuilder::create('/api/signals/unmatched')
                ->controller(fn () => $this->signalController()->listUnmatched(Request::createFromGlobals()))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.enrich.request',
            RouteBuilder::create('/api/leads/{id}/enrich')
                ->controller(fn (string $id) => $this->enrichmentController()->requestEnrichment(Request::createFromGlobals(), $id))
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.enrich.receive',
            RouteBuilder::create('/api/leads/{id}/enrichment')
                ->controller(fn (string $id) => $this->enrichmentController()->receiveEnrichment(Request::createFromGlobals(), $id))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.signals.list',
            RouteBuilder::create('/api/leads/{id}/signals')
                ->controller(fn (string $id) => $this->enrichmentController()->listSignals($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.enrichments.list',
            RouteBuilder::create('/api/leads/{id}/enrichments')
                ->controller(fn (string $id) => $this->enrichmentController()->listEnrichments($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}
