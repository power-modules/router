<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DynamicRoute;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final class DynamicRouteModule implements PowerModule, HasRoutes
{
    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(DynamicRouteController::class, DynamicRouteController::class);
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/users/{id}', DynamicRouteController::class),
        ];
    }
}
