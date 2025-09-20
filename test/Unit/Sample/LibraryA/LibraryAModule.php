<?php

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

class LibraryAModule implements PowerModule, HasRoutes, HasMiddleware
{
    public function getMiddleware(): array
    {
        return [
            ModuleMiddlewareA::class,
        ];
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/feature-a', LibraryAController::class),
            Route::get('/feature-b', LibraryAController::class, 'featureB')->addMiddleware(RouteMiddlewareA::class),
            Route::get('/feature-c', LibraryAController::class, 'featureC'),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(
            LibraryAController::class,
            LibraryAController::class,
        );
    }
}
