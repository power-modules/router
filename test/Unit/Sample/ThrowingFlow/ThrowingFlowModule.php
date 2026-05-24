<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\ThrowingFlow;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasCustomRouteSlug;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final class ThrowingFlowModule implements PowerModule, HasRoutes, HasMiddleware, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/throwing-flow';
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/controller', ThrowingController::class),
            Route::get('/domain-exception', ThrowingController::class),
            Route::get('/route-middleware', ThrowingController::class)
                ->addMiddleware(ThrowingRouteMiddleware::class),
            Route::get('/module-middleware', ThrowingController::class),
        ];
    }

    public function getMiddleware(): array
    {
        return [
            ThrowingModuleMiddleware::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(ThrowingController::class, ThrowingController::class);
        $container->set(ThrowingRouteMiddleware::class, ThrowingRouteMiddleware::class);
        $container->set(ThrowingModuleMiddleware::class, ThrowingModuleMiddleware::class);
    }
}
