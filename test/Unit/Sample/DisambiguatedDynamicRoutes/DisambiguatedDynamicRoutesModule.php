<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DisambiguatedDynamicRoutes;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final class DisambiguatedDynamicRoutesModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/reports/{year}/summary', DisambiguatedDynamicRoutesHandler::class),
            Route::get('/reports/{slug}/details', DisambiguatedDynamicRoutesHandler::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(DisambiguatedDynamicRoutesHandler::class, DisambiguatedDynamicRoutesHandler::class);
    }
}
