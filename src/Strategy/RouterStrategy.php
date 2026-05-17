<?php

declare(strict_types=1);

namespace Modular\Router\Strategy;

use League\Route\ContainerAwareInterface;
use League\Route\Strategy\StrategyInterface as LeagueRouteStrategyInterface;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Router\Contract\RouterStrategyInterface;
use Psr\Http\Message\ResponseInterface;

abstract class RouterStrategy implements RouterStrategyInterface
{
    /**
     * @var list<callable(ResponseInterface):ResponseInterface>
     */
    private array $responseDecorators = [];

    /**
     * @param callable(ResponseInterface):ResponseInterface $decorator
     */
    public function addResponseDecorator(callable $decorator): static
    {
        $this->responseDecorators[] = $decorator;

        return $this;
    }

    final public function createLeagueRouteStrategy(
        ConfigurableContainerInterface $container,
    ): LeagueRouteStrategyInterface {
        $strategy = $this->buildLeagueRouteStrategy();

        if ($strategy instanceof ContainerAwareInterface) {
            $strategy->setContainer($container);
        }

        foreach ($this->responseDecorators as $responseDecorator) {
            $strategy->addResponseDecorator($responseDecorator);
        }

        return $strategy;
    }

    abstract protected function buildLeagueRouteStrategy(): LeagueRouteStrategyInterface;
}
