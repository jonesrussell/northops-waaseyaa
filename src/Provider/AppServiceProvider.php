<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\Api\LeadController;
use App\Controller\DashboardController;
use App\Controller\MarketingController;
use App\Domain\Pipeline\EventSubscriber\LeadCreatedSubscriber;
use App\Domain\Pipeline\EventSubscriber\LeadQualifiedSubscriber;
use App\Domain\Pipeline\EventSubscriber\StageChangedSubscriber;
use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Pipeline\RoutingService;
use App\Domain\Pipeline\ProspectScoringService;
use App\Domain\Pipeline\RfpImportService;
use App\Domain\Qualification\CompanyProfile;
use App\Domain\Qualification\QualificationService;
use App\Surface\Action\LeadBoardConfigAction;
use App\Surface\Action\LeadQualifyAction;
use App\Surface\Action\LeadTransitionStageAction;
use App\Surface\LeadSurfaceHost;
use App\Support\DiscordNotifier;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\HttpClient\StreamHttpClient;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\SSR\SsrServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    private ?MarketingController $controller = null;
    private ?LeadController $apiController = null;
    private ?DashboardController $dashboardController = null;
    private ?LeadSurfaceHost $surfaceHost = null;

    private ?DiscordNotifier $discordNotifier = null;
    private ?LeadManager $leadManager = null;
    private ?LeadFactory $leadFactory = null;
    private ?QualificationService $qualificationService = null;
    private ?LeadQualifiedSubscriber $leadQualifiedSubscriber = null;
    private ?RfpImportService $rfpImportService = null;

    public function register(): void {}

    // ---------------------------------------------------------------
    // Shared service builders (lazy, cached)
    // ---------------------------------------------------------------

    private function getDiscordNotifier(): DiscordNotifier
    {
        return $this->discordNotifier ??= $this->buildDiscordNotifier();
    }

    private function buildDiscordNotifier(): DiscordNotifier
    {
        return new DiscordNotifier(
            new StreamHttpClient(timeout: 5.0),
            $this->config['discord']['webhook_url'] ?? '',
        );
    }

    private function getLeadManager(): LeadManager
    {
        if ($this->leadManager === null) {
            $etm = $this->resolve(EntityTypeManager::class);
            $notifier = $this->getDiscordNotifier();
            $leadCreatedSubscriber = new LeadCreatedSubscriber($etm, $notifier);
            $stageChangedSubscriber = new StageChangedSubscriber($etm, $notifier);
            $this->leadManager = new LeadManager($etm, $leadCreatedSubscriber, $stageChangedSubscriber);
        }

        return $this->leadManager;
    }

    private function getLeadFactory(): LeadFactory
    {
        return $this->leadFactory ??= new LeadFactory(
            $this->getLeadManager(),
            $this->resolve(EntityTypeManager::class),
            new RoutingService(),
        );
    }

    private function getQualificationService(): QualificationService
    {
        return $this->qualificationService ??= new QualificationService(
            new StreamHttpClient(),
            $this->config['pipeline']['anthropic_api_key'] ?? '',
            new CompanyProfile($this->config['pipeline']['company_profile'] ?? ''),
            new ProspectScoringService(),
        );
    }

    private function getLeadQualifiedSubscriber(): LeadQualifiedSubscriber
    {
        return $this->leadQualifiedSubscriber ??= new LeadQualifiedSubscriber(
            $this->resolve(EntityTypeManager::class),
            $this->getDiscordNotifier(),
        );
    }

    private function getRfpImportService(): RfpImportService
    {
        return $this->rfpImportService ??= new RfpImportService(
            $this->getLeadFactory(),
            $this->getLeadManager(),
            $this->getQualificationService(),
            $this->getLeadQualifiedSubscriber(),
            new StreamHttpClient(),
            $this->config['pipeline']['northcloud_url'] ?? '',
        );
    }

    // ---------------------------------------------------------------
    // Controller builders
    // ---------------------------------------------------------------

    private function controller(): MarketingController
    {
        if ($this->controller === null) {
            $twig = SsrServiceProvider::getTwigEnvironment();
            if ($twig === null) {
                throw new \RuntimeException('Twig environment not initialized');
            }

            $etm = $this->resolve(EntityTypeManager::class);
            $defaultBrandId = $this->resolveDefaultBrandId($etm);

            $this->controller = new MarketingController(
                $twig,
                $etm,
                $this->getDiscordNotifier(),
                $this->getLeadFactory(),
                $defaultBrandId,
            );
        }

        return $this->controller;
    }

    private function resolveDefaultBrandId(EntityTypeManager $etm): ?int
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
            // Brand table may not exist yet during initial setup
        }

        return null;
    }

    private function dashboardController(): DashboardController
    {
        if ($this->dashboardController === null) {
            $twig = SsrServiceProvider::getTwigEnvironment();
            if ($twig === null) {
                throw new \RuntimeException('Twig environment not initialized');
            }

            $this->dashboardController = new DashboardController($twig);
        }

        return $this->dashboardController;
    }

    private function apiController(): LeadController
    {
        if ($this->apiController === null) {
            $this->apiController = new LeadController(
                $this->resolve(EntityTypeManager::class),
                $this->getLeadManager(),
                $this->getLeadFactory(),
                $this->getQualificationService(),
                $this->getRfpImportService(),
                $this->getLeadQualifiedSubscriber(),
                $this->config,
            );
        }

        return $this->apiController;
    }

    private function surfaceHost(): LeadSurfaceHost
    {
        if ($this->surfaceHost === null) {
            $etm = $this->resolve(EntityTypeManager::class);

            $this->surfaceHost = new LeadSurfaceHost(
                entityTypeManager: $etm,
                boardConfigAction: new LeadBoardConfigAction(),
                transitionAction: new LeadTransitionStageAction($etm),
                qualifyAction: new LeadQualifyAction($etm, $this->getQualificationService()),
                schemaPresenter: new SchemaPresenter(),
            );
        }

        return $this->surfaceHost;
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'marketing.home',
            RouteBuilder::create('/')
                ->controller(fn () => new SsrResponse($this->controller()->home()))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'marketing.about',
            RouteBuilder::create('/about')
                ->controller(fn () => new SsrResponse($this->controller()->about()))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'marketing.services',
            RouteBuilder::create('/services')
                ->controller(fn () => new SsrResponse($this->controller()->servicesIndex()))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $validServices = [
            'web-application-engineering',
            'content-data-pipelines',
            'devops-cicd',
            'ai-engineering',
        ];

        foreach ($validServices as $slug) {
            $router->addRoute(
                "marketing.services.{$slug}",
                RouteBuilder::create("/services/{$slug}")
                    ->controller(fn () => new SsrResponse($this->controller()->serviceDetail($slug)))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );
        }

        $router->addRoute(
            'marketing.contact',
            RouteBuilder::create('/contact')
                ->controller(function () {
                    $request = Request::createFromGlobals();

                    if ($request->getMethod() === 'POST') {
                        $result = $this->controller()->submitContact($request);

                        if ($result instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
                            return $result;
                        }

                        return new SsrResponse($result);
                    }

                    return new SsrResponse($this->controller()->contact($request));
                })
                ->allowAll()
                ->methods('GET', 'POST')
                ->build(),
        );

        // ---------------------------------------------------------------
        // Admin routes
        // ---------------------------------------------------------------

        $router->addRoute(
            'admin.dashboard',
            RouteBuilder::create('/admin')
                ->controller(fn () => new SsrResponse($this->dashboardController()->index()))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.leads',
            RouteBuilder::create('/admin/leads')
                ->controller(fn () => new SsrResponse($this->dashboardController()->leadList()))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.lead.detail',
            RouteBuilder::create('/admin/leads/{id}')
                ->controller(fn (string $id) => new SsrResponse($this->dashboardController()->leadDetail($id)))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.settings',
            RouteBuilder::create('/admin/settings')
                ->controller(fn () => new SsrResponse($this->dashboardController()->settings()))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // ---------------------------------------------------------------
        // API routes
        // ---------------------------------------------------------------

        $router->addRoute(
            'api.leads.list',
            RouteBuilder::create('/api/leads')
                ->controller(fn () => $this->apiController()->listLeads(Request::createFromGlobals()))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.create',
            RouteBuilder::create('/api/leads')
                ->controller(fn () => $this->apiController()->createLead(Request::createFromGlobals()))
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.import',
            RouteBuilder::create('/api/leads/import')
                ->controller(fn () => $this->apiController()->importLeads(Request::createFromGlobals()))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.get',
            RouteBuilder::create('/api/leads/{id}')
                ->controller(fn (string $id) => $this->apiController()->getLead($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.update',
            RouteBuilder::create('/api/leads/{id}')
                ->controller(fn (string $id) => $this->apiController()->updateLead(Request::createFromGlobals(), $id))
                ->requireAuthentication()
                ->methods('PATCH')
                ->build(),
        );

        $router->addRoute(
            'api.leads.delete',
            RouteBuilder::create('/api/leads/{id}')
                ->controller(fn (string $id) => $this->apiController()->deleteLead($id))
                ->requireAuthentication()
                ->methods('DELETE')
                ->build(),
        );

        $router->addRoute(
            'api.leads.change_stage',
            RouteBuilder::create('/api/leads/{id}/stage')
                ->controller(fn (string $id) => $this->apiController()->changeStage(Request::createFromGlobals(), $id))
                ->requireAuthentication()
                ->methods('PATCH')
                ->build(),
        );

        $router->addRoute(
            'api.leads.qualify',
            RouteBuilder::create('/api/leads/{id}/qualify')
                ->controller(fn (string $id) => $this->apiController()->qualifyLead($id))
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.activity.list',
            RouteBuilder::create('/api/leads/{id}/activity')
                ->controller(fn (string $id) => $this->apiController()->listActivity($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.activity.create',
            RouteBuilder::create('/api/leads/{id}/activity')
                ->controller(fn (string $id) => $this->apiController()->createActivity(Request::createFromGlobals(), $id))
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.attachments.list',
            RouteBuilder::create('/api/leads/{id}/attachments')
                ->controller(fn (string $id) => $this->apiController()->listAttachments($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.brands.list',
            RouteBuilder::create('/api/brands')
                ->controller(fn () => $this->apiController()->listBrands())
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.config',
            RouteBuilder::create('/api/config')
                ->controller(fn () => $this->apiController()->getConfig())
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // ---------------------------------------------------------------
        // Admin surface routes (custom LeadSurfaceHost)
        // ---------------------------------------------------------------

        AdminSurfaceServiceProvider::registerRoutes($router, $this->surfaceHost());
    }
}
