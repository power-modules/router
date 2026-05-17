<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DuplicateRoutes;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAController;

final class DuplicateRoutesModule implements PowerModule, HasRoutes
{
    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(LibraryAController::class, LibraryAController::class);
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/users', LibraryAController::class),
            Route::get('/users', LibraryAController::class, 'featureB'),
        ];
    }
}
