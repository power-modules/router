<?php

declare(strict_types=1);

namespace Modular\Framework\Test\PowerModule\Setup;

use Modular\Framework\App\Config\Config;
use Modular\Framework\Config\Contract\HasConfig;
use Modular\Framework\Config\Contract\PowerModuleConfig;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use PHPUnit\Framework\TestCase;

class RoutingSetupTest extends TestCase
{
    public function testSetupRegistersRoutesWithConfig(): void
    {
        $router = $this->createMock(ModularRouterInterface::class);
        $router->expects($this->once())
            ->method('registerPowerModuleRoutes');
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->method('has')->with(ModularRouterInterface::class)->willReturn(true);
        $moduleContainerMock = $this->createMock(ConfigurableContainerInterface::class);
        $moduleConfig = $this->createMock(\Modular\Framework\Config\Contract\PowerModuleConfig::class);
        $module = new class ($moduleConfig) implements HasConfig, PowerModule {
            private PowerModuleConfig $config;
            public function __construct(PowerModuleConfig $config)
            {
                $this->config = $config;
            }
            public function getConfig(): PowerModuleConfig
            {
                return $this->config;
            }
            public function setConfig(PowerModuleConfig $config): void
            {
                $this->config = $config;
            }
            public function register(ConfigurableContainerInterface $container): void
            {
            }
        };
        $moduleClass = get_class($module);
        $rootContainer->method('get')->willReturnMap([
            [ModularRouterInterface::class, $router],
            [$moduleClass, $moduleContainerMock],
        ]);
        $dto = new PowerModuleSetupDto(
            SetupPhase::Post,
            $module,
            $rootContainer,
            $this->createMock(ConfigurableContainerInterface::class),
            $this->createMock(Config::class),
        );
        (new RoutingSetup())->setup($dto);
    }

    public function testSetupRegistersRoutesWithoutConfig(): void
    {
        $router = $this->createMock(ModularRouterInterface::class);
        $router->expects($this->once())
            ->method('registerPowerModuleRoutes');
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->method('has')->with(ModularRouterInterface::class)->willReturn(true);
        $moduleContainerMock = $this->createMock(ConfigurableContainerInterface::class);
        $module = new class () implements PowerModule {
            public function register(ConfigurableContainerInterface $container): void
            {
            }
        };
        $moduleClass = get_class($module);
        $rootContainer->method('get')->willReturnMap([
            [ModularRouterInterface::class, $router],
            [$moduleClass, $moduleContainerMock],
        ]);
        $dto = new PowerModuleSetupDto(
            SetupPhase::Post,
            $module,
            $rootContainer,
            $this->createMock(ConfigurableContainerInterface::class),
            $this->createMock(Config::class),
        );
        (new RoutingSetup())->setup($dto);
    }

    public function testSetupDoesNothingIfNotPostPhase(): void
    {
        $router = $this->createMock(ModularRouterInterface::class);
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->expects($this->never())->method('get');
        $module = new class () implements PowerModule {
            public function register(ConfigurableContainerInterface $container): void
            {
            }
        };
        $dto = new PowerModuleSetupDto(
            SetupPhase::Pre,
            $module,
            $rootContainer,
            $this->createMock(ConfigurableContainerInterface::class),
            $this->createMock(Config::class),
        );
        (new RoutingSetup())->setup($dto);
    }

    public function testSetupDoesNothingIfRouterNotPresent(): void
    {
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $rootContainer->method('has')->with(ModularRouterInterface::class)->willReturn(false);
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
            $this->createMock(ConfigurableContainerInterface::class),
            $this->createMock(Config::class),
        );
        (new RoutingSetup())->setup($dto);
    }
}
