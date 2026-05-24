<?php

declare(strict_types=1);

namespace Modular\Router\PowerModule\Setup;

use Modular\Framework\PowerModule\Contract\PowerModuleSetup;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\RoutingModule;

class RoutingSetup implements PowerModuleSetup
{
    /**
     * @return list<PowerModuleSetup>
     */
    public static function withDefaults(): array
    {
        return [
            new HttpEntrypointMiddlewareSetup(RoutingModule::class),
            new ResponseDecoratorChainSetup(RoutingModule::class),
            new SyntheticResponseSetup(RoutingModule::class),
            new self(),
        ];
    }

    public function setup(PowerModuleSetupDto $powerModuleSetupDto): void
    {
        if ($powerModuleSetupDto->setupPhase !== SetupPhase::Post) {
            return;
        }

        if (!$powerModuleSetupDto->powerModule instanceof HasRoutes) {
            return;
        }

        if ($powerModuleSetupDto->rootContainer->has(ModularRouterInterface::class) === false) {
            return;
        }

        /** @var ModularRouterInterface $router */
        $router = $powerModuleSetupDto->rootContainer->get(ModularRouterInterface::class);
        $router->registerPowerModuleRoutes(
            $powerModuleSetupDto->powerModule,
            $powerModuleSetupDto->moduleContainer,
        );
    }
}
