<?php

declare(strict_types=1);

namespace Modular\Router\Config;

use Modular\Framework\Config\Contract\PowerModuleConfig;
use Modular\Router\Strategy\ApplicationRouterStrategy;

class Config extends PowerModuleConfig
{
    public static function create(): static
    {
        $defaultStrategy = new ApplicationRouterStrategy();

        return parent::create()->set(
            Setting::Strategy,
            $defaultStrategy,
        );
    }

    public function getConfigFilename(): string
    {
        return 'modular_router';
    }
}
