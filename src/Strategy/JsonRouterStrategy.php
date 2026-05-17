<?php

declare(strict_types=1);

namespace Modular\Router\Strategy;

use League\Route\Strategy\JsonStrategy;
use League\Route\Strategy\StrategyInterface as LeagueRouteStrategyInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class JsonRouterStrategy extends RouterStrategy
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly int $jsonFlags = 0,
    ) {
    }

    protected function buildLeagueRouteStrategy(): LeagueRouteStrategyInterface
    {
        return new JsonStrategy($this->responseFactory, $this->jsonFlags);
    }
}
