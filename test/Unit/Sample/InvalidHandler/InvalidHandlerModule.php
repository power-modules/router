<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\InvalidHandler;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final class InvalidHandlerModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/invalid', InvalidHandlerController::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(InvalidHandlerController::class, InvalidHandlerController::class);
    }
}
