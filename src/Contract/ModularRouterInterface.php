<?php

namespace Modular\Router\Contract;

use Modular\Framework\PowerModule\Contract\PowerModule;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface ModularRouterInterface extends RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer,
    ): void;

    /**
     * Adds a response decorator to the router. Affects all routes across all modules.
     * @param callable(ResponseInterface): ResponseInterface $decorator
     */
    public function addResponseDecorator(callable $decorator): ModularRouterInterface;
}
