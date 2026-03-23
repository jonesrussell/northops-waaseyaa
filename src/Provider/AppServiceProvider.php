<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\MarketingController;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrResponse;

final class AppServiceProvider extends ServiceProvider
{
    private ?MarketingController $controller = null;

    public function register(): void {}

    private function controller(): MarketingController
    {
        if ($this->controller === null) {
            $this->controller = new MarketingController(
                $this->resolve(EntityTypeManager::class),
            );
        }

        return $this->controller;
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
    }
}
