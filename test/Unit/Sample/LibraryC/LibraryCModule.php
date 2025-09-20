<?php

namespace Modular\Router\Test\Unit\Sample\LibraryC;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasCustomRouteSlug;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;
use Modular\Router\Test\Unit\Sample\LibraryA\RouteMiddlewareA;

class LibraryCModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/already-has-slash';
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/test', LibraryCController::class),
            Route::get('/no-middleware', LibraryCController::class)
                ->addMiddleware(RouteMiddlewareA::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(
            LibraryCController::class,
            LibraryCController::class,
        );
    }
}
