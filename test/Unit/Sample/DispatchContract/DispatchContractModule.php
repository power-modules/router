<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DispatchContract;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasResponseDecorators;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;
use Psr\Http\Message\ResponseInterface;

final class DispatchContractModule implements PowerModule, HasRoutes, HasMiddleware, HasResponseDecorators
{
    public function getRoutes(): array
    {
        return [
            Route::get('/users/all', DispatchContractStaticUserHandler::class)
                ->addResponseDecorator(
                    static fn (ResponseInterface $response): ResponseInterface => $response->withHeader('X-Decorator-Order', trim($response->getHeaderLine('X-Decorator-Order') . ',route', ',')),
                ),
            Route::get('/users/{id}', DispatchContractDynamicUserHandler::class),
            Route::get('/ordered', DispatchContractController::class)
                ->addMiddleware(RouteMiddleware::class)
                ->addResponseDecorator(
                    static fn (ResponseInterface $response): ResponseInterface => $response->withHeader('X-Decorator-Order', trim($response->getHeaderLine('X-Decorator-Order') . ',route', ',')),
                ),
            Route::get('/slash', DispatchContractController::class),
            Route::post('/method-check', DispatchContractController::class),
            Route::get('/method-check', DispatchContractController::class),
        ];
    }

    public function getMiddleware(): array
    {
        return [
            ModuleMiddleware::class,
        ];
    }

    public function getResponseDecorators(): array
    {
        return [
            static fn (ResponseInterface $response): ResponseInterface => $response->withHeader('X-Decorator-Order', trim($response->getHeaderLine('X-Decorator-Order') . ',module', ',')),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(DispatchContractController::class, DispatchContractController::class);
        $container->set(DispatchContractStaticUserHandler::class, DispatchContractStaticUserHandler::class);
        $container->set(DispatchContractDynamicUserHandler::class, DispatchContractDynamicUserHandler::class);
        $container->set(ModuleMiddleware::class, ModuleMiddleware::class);
        $container->set(RouteMiddleware::class, RouteMiddleware::class);
    }
}
