<?php

namespace Modular\Router\Test\Unit\Sample\LibraryB;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasCustomRouteSlug;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

class LibraryBModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return 'custom-api';
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/endpoint', LibraryBController::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(
            LibraryBController::class,
            LibraryBController::class,
        );
    }
}
