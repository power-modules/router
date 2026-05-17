<?php

declare(strict_types=1);

namespace Modular\Router\Strategy;

use League\Route\Strategy\ApplicationStrategy;
use League\Route\Strategy\StrategyInterface as LeagueRouteStrategyInterface;

final class ApplicationRouterStrategy extends RouterStrategy
{
    protected function buildLeagueRouteStrategy(): LeagueRouteStrategyInterface
    {
        return new ApplicationStrategy();
    }
}