<?php

namespace Modular\Router\PowerModule\Setup;

use Modular\Framework\Config\Contract\HasConfig;
use Modular\Framework\PowerModule\Contract\CanSetupPowerModule;
use Modular\Framework\PowerModule\PowerModuleHelper;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Router\Contract\ModularRouterInterface;

class RoutingSetup implements CanSetupPowerModule
{
    public function setup(PowerModuleSetupDto $powerModuleSetupDto): void
    {
        if ($powerModuleSetupDto->setupPhase !== SetupPhase::Post) {
            return;
        }

        if ($powerModuleSetupDto->rootContainer->has(ModularRouterInterface::class) === false) {
            return;
        }

        /** @var ModularRouterInterface $router */
        $router = $powerModuleSetupDto->rootContainer->get(ModularRouterInterface::class);

        if ($powerModuleSetupDto->powerModule instanceof HasConfig) {
            $moduleConfig = $powerModuleSetupDto->powerModule->getConfig();
        } else {
            $moduleConfig = null;
        }

        $router->registerPowerModuleRoutes(
            $powerModuleSetupDto->powerModule,
            $powerModuleSetupDto->rootContainer->get(PowerModuleHelper::getPowerModuleName($powerModuleSetupDto->powerModule)),
            $moduleConfig,
        );
    }
}
