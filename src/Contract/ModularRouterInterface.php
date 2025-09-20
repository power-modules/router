<?php

namespace Modular\Router\Contract;

use Modular\Framework\Config\Contract\PowerModuleConfig;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface ModularRouterInterface extends RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer,
        ?PowerModuleConfig $powerModuleConfig,
    ): void;

    public function addResponseDecorator(callable $decorator): ModularRouterInterface;
}
