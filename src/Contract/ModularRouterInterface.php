<?php

namespace Modular\Router\Contract;

use Modular\Framework\PowerModule\Contract\PowerModule;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface ModularRouterInterface extends RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer,
    ): void;

    public function addResponseDecorator(callable $decorator): ModularRouterInterface;
}
