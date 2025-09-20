<?php

declare(strict_types=1);

namespace Modular\Router;

use Modular\Framework\Config\Contract\HasConfig;
use Modular\Framework\Config\Contract\HasConfigTrait;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;
use Modular\Router\Contract\ModularRouterInterface;

class RouterModule implements PowerModule, ExportsComponents, HasConfig
{
    use HasConfigTrait;

    public function __construct(
    ) {
        $this->powerModuleConfig = Config::create();
    }

    public static function exports(): array
    {
        return [
            ModularRouterInterface::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(
            ModularRouterInterface::class,
            Router::class,
        )->addArguments([
            $this->powerModuleConfig->get(Setting::Strategy),
        ]);
    }
}
