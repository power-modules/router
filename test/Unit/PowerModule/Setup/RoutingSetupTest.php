<?php

declare(strict_types=1);

namespace Modular\Framework\Test\PowerModule\Setup;

use Modular\Framework\App\Config\Config;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\HttpEntrypointMiddlewareSetup;
use Modular\Router\PowerModule\Setup\ResponseDecoratorChainSetup;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\PowerModule\Setup\SyntheticResponseSetup;
use Modular\Router\RoutingModule;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAModule;
use PHPUnit\Framework\TestCase;

class RoutingSetupTest extends TestCase
{
    public function testWithDefaultsReturnsExpectedSetupBundle(): void
    {
        self::assertEquals(
            [
                new HttpEntrypointMiddlewareSetup(RoutingModule::class),
                new ResponseDecoratorChainSetup(RoutingModule::class),
                new SyntheticResponseSetup(RoutingModule::class),
                new RoutingSetup(),
            ],
            RoutingSetup::withDefaults(),
        );
    }

    public function testSetupRegistersCorrectModuleRoutes(): void
    {
        $router = $this->createMock(ModularRouterInterface::class);
        $router->expects($this->once())->method('registerPowerModuleRoutes');

        $rootContainer = new ConfigurableContainer();
        $rootContainer->set(ModularRouterInterface::class, $router);

        $module = new LibraryAModule();
        $moduleContainer = new ConfigurableContainer();
        $module->register($moduleContainer);
        $rootContainer->set(LibraryAModule::class, $moduleContainer);

        $dto = new PowerModuleSetupDto(
            SetupPhase::Post,
            $module,
            $rootContainer,
            $moduleContainer,
            $this->createStub(Config::class),
        );

        new RoutingSetup()->setup($dto);
    }

    public function testSetupDoesNothingIfNotPostPhase(): void
    {
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->expects($this->never())->method('get');
        $dto = new PowerModuleSetupDto(
            SetupPhase::Pre,
            new LibraryAModule(),
            $rootContainer,
            $this->createStub(ConfigurableContainerInterface::class),
            $this->createStub(Config::class),
        );
        new RoutingSetup()->setup($dto);
    }

    public function testSetupDoesNothingIfModuleDoesNotImplementHasRoutes(): void
    {
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->method('has')->with(ModularRouterInterface::class)->willReturn(true);
        $rootContainer->expects($this->never())->method('get');
        $module = new class () implements PowerModule {
            public function register(ConfigurableContainerInterface $container): void
            {
            }
        };
        $dto = new PowerModuleSetupDto(
            SetupPhase::Post,
            $module,
            $rootContainer,
            $this->createStub(ConfigurableContainerInterface::class),
            $this->createStub(Config::class),
        );
        new RoutingSetup()->setup($dto);
    }

    public function testSetupDoesNothingIfRouterNotPresent(): void
    {
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->method('has')->with(ModularRouterInterface::class)->willReturn(false);
        $rootContainer->expects($this->never())->method('get');
        $dto = new PowerModuleSetupDto(
            SetupPhase::Post,
            new LibraryAModule(),
            $rootContainer,
            $this->createStub(ConfigurableContainerInterface::class),
            $this->createStub(Config::class),
        );
        new RoutingSetup()->setup($dto);
    }
}
