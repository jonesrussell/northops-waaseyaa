<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute('auth.login', RouteBuilder::create('/login')
            ->controller('App\\Controller\\AuthController::loginForm')
            ->allowAll()
            ->methods('GET')
            ->render()
            ->build());

        $router->addRoute('auth.login.submit', RouteBuilder::create('/login')
            ->controller('App\\Controller\\AuthController::submitLogin')
            ->allowAll()
            ->methods('POST')
            ->render()
            ->build());

        $router->addRoute('auth.logout', RouteBuilder::create('/logout')
            ->controller('App\\Controller\\AuthController::logout')
            ->allowAll()
            ->methods('GET', 'POST')
            ->build());
    }
}
