<?php

namespace Modular\Router\Test\Unit\Sample\LibraryC;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasCustomRouteSlug;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

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
