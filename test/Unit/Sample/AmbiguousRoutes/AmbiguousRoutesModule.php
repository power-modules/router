<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\AmbiguousRoutes;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final class AmbiguousRoutesModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/reports/{year}', AmbiguousRoutesController::class),
            Route::get('/reports/{slug}', AmbiguousRoutesController::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(AmbiguousRoutesController::class, AmbiguousRoutesController::class);
    }
}
