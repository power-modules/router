<?php

declare(strict_types=1);

namespace Modular\Router\Config;

use League\Route\Strategy\ApplicationStrategy;
use Modular\Framework\Config\Contract\PowerModuleConfig;

class Config extends PowerModuleConfig
{
    public static function create(): static
    {
        $defaultStrategy = new ApplicationStrategy();

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
