<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\MarketingController;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment as Twig;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(MarketingController::class, fn () => new MarketingController(
            $this->resolve(Twig::class),
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $controller = $this->resolve(MarketingController::class);

        $router->addRoute(
            'marketing.home',
            RouteBuilder::create('/')
                ->controller(fn () => ['type' => 'html', 'status' => 200, 'content' => $controller->home()])
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'marketing.contact',
            RouteBuilder::create('/contact')
                ->controller(function () use ($controller) {
                    $request = Request::createFromGlobals();
                    return ['type' => 'html', 'status' => 200, 'content' => $controller->contact($request)];
                })
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'marketing.contact.submit',
            RouteBuilder::create('/contact')
                ->controller(function () use ($controller) {
                    $request = Request::createFromGlobals();
                    $result = $controller->submitContact($request);

                    if ($result instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
                        return $result;
                    }

                    return ['type' => 'html', 'status' => 200, 'content' => $result];
                })
                ->allowAll()
                ->methods('POST')
                ->build(),
        );
    }
}
