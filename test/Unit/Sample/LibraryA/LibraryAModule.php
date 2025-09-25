<?php

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasResponseDecorators;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;
use Psr\Http\Message\ResponseInterface;

class LibraryAModule implements PowerModule, HasRoutes, HasMiddleware, HasResponseDecorators
{
    public function getResponseDecorators(): array
    {
        return [
            static fn (ResponseInterface $response): ResponseInterface => $response->withHeader('X-Library-A-Static', 'true'),
            function (ResponseInterface $response): ResponseInterface {
                return $response->withHeader('X-Library-A-Closure', 'true');
            },
            new BasicResponseDecorator(),
        ];
    }

    public function getMiddleware(): array
    {
        return [
            ModuleMiddlewareA::class,
        ];
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/feature-a', LibraryAController::class)
                ->addResponseDecorator(
                    static fn (ResponseInterface $response): ResponseInterface => $response->withHeader('X-Library-A-Route', 'true'),
                ),
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

        $container->set(
            RouteMiddlewareA::class,
            RouteMiddlewareA::class,
        );

        $container->set(
            ModuleMiddlewareA::class,
            ModuleMiddlewareA::class,
        );
    }
}
