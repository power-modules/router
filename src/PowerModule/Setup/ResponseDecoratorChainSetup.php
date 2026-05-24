<?php

declare(strict_types=1);

namespace Modular\Router\PowerModule\Setup;

use LogicException;
use Modular\Framework\Container\InstanceResolver\InstanceViaContainerResolver;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\PowerModule\Contract\PowerModuleSetup;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\RouterModule;

final class ResponseDecoratorChainSetup implements PowerModuleSetup
{
    private ?PowerModuleSetupDto $exportingModule = null;

    /**
     * @param class-string<PowerModule> $exportingModuleClassString
     */
    public function __construct(
        private readonly string $exportingModuleClassString,
    ) {
    }

    public function setup(PowerModuleSetupDto $powerModuleSetupDto): void
    {
        if ($this->exportingModule === null && $powerModuleSetupDto->powerModule::class === $this->exportingModuleClassString) {
            $this->exportingModule = $powerModuleSetupDto;
        }

        if ($powerModuleSetupDto->setupPhase !== SetupPhase::Post) {
            return;
        }

        if ($powerModuleSetupDto->rootContainer->has(ModularRouterInterface::class) === false) {
            return;
        }

        if ($powerModuleSetupDto->powerModule instanceof RouterModule === false) {
            return;
        }

        if ($this->exportingModule === null) {
            throw new LogicException(
                "Exporting module with class {$this->exportingModuleClassString} was not found during setup.",
            );
        }

        $powerModuleSetupDto->moduleContainer->set(
            ResponseDecoratorChainInterface::class,
            $this->exportingModule->moduleContainer,
            InstanceViaContainerResolver::class,
        );
    }
}
