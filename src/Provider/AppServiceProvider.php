<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\Api\LeadController;
use App\Controller\DashboardController;
use App\Controller\MarketingController;
use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Qualification\CompanyProfile;
use App\Domain\Qualification\QualificationService;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrResponse;

final class AppServiceProvider extends ServiceProvider
{
    private ?MarketingController $controller = null;
    private ?LeadController $apiController = null;
    private ?DashboardController $dashboardController = null;

    public function register(): void {}

    private function controller(): MarketingController
    {
        if ($this->controller === null) {
            $this->controller = new MarketingController(
                $this->resolve(EntityTypeManager::class),
                $this->config['discord']['webhook_url'] ?? '',
            );
        }

        return $this->controller;
    }

    private function dashboardController(): DashboardController
    {
        if ($this->dashboardController === null) {
            $this->dashboardController = new DashboardController();
        }

        return $this->dashboardController;
    }

    private function apiController(): LeadController
    {
        if ($this->apiController === null) {
            $etm = $this->resolve(EntityTypeManager::class);
            $leadManager = new LeadManager($etm);
            $leadFactory = new LeadFactory($leadManager, $etm);
            $qualificationService = new QualificationService(
                $this->config['pipeline']['anthropic_api_key'] ?? '',
                new CompanyProfile($this->config['pipeline']['company_profile'] ?? ''),
            );

            $this->apiController = new LeadController(
                $etm,
                $leadManager,
                $leadFactory,
                $qualificationService,
                $this->config,
            );
        }

        return $this->apiController;
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
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.leads',
            RouteBuilder::create('/admin/leads')
                ->controller(fn () => new SsrResponse($this->dashboardController()->leadList()))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.lead.detail',
            RouteBuilder::create('/admin/leads/{id}')
                ->controller(fn (string $id) => new SsrResponse($this->dashboardController()->leadDetail($id)))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.settings',
            RouteBuilder::create('/admin/settings')
                ->controller(fn () => new SsrResponse($this->dashboardController()->settings()))
                ->allowAll()
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
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.create',
            RouteBuilder::create('/api/leads')
                ->controller(fn () => $this->apiController()->createLead(Request::createFromGlobals()))
                ->allowAll()
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
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.update',
            RouteBuilder::create('/api/leads/{id}')
                ->controller(fn (string $id) => $this->apiController()->updateLead(Request::createFromGlobals(), $id))
                ->allowAll()
                ->methods('PATCH')
                ->build(),
        );

        $router->addRoute(
            'api.leads.delete',
            RouteBuilder::create('/api/leads/{id}')
                ->controller(fn (string $id) => $this->apiController()->deleteLead($id))
                ->allowAll()
                ->methods('DELETE')
                ->build(),
        );

        $router->addRoute(
            'api.leads.change_stage',
            RouteBuilder::create('/api/leads/{id}/stage')
                ->controller(fn (string $id) => $this->apiController()->changeStage(Request::createFromGlobals(), $id))
                ->allowAll()
                ->methods('PATCH')
                ->build(),
        );

        $router->addRoute(
            'api.leads.qualify',
            RouteBuilder::create('/api/leads/{id}/qualify')
                ->controller(fn (string $id) => $this->apiController()->qualifyLead($id))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.activity.list',
            RouteBuilder::create('/api/leads/{id}/activity')
                ->controller(fn (string $id) => $this->apiController()->listActivity($id))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.leads.activity.create',
            RouteBuilder::create('/api/leads/{id}/activity')
                ->controller(fn (string $id) => $this->apiController()->createActivity(Request::createFromGlobals(), $id))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.leads.attachments.list',
            RouteBuilder::create('/api/leads/{id}/attachments')
                ->controller(fn (string $id) => $this->apiController()->listAttachments($id))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.brands.list',
            RouteBuilder::create('/api/brands')
                ->controller(fn () => $this->apiController()->listBrands())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.config',
            RouteBuilder::create('/api/config')
                ->controller(fn () => $this->apiController()->getConfig())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
