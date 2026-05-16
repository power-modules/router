<?php

declare(strict_types=1);

namespace Modular\Router\Bench\Fixtures;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasCustomRouteSlug;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final readonly class SyntheticModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    /**
     * @param list<Route> $routes
     */
    public function __construct(
        private string $routeSlug,
        private array $routes,
    ) {
    }

    public function getRouteSlug(): string
    {
        return $this->routeSlug;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(BenchmarkController::class, BenchmarkController::class);
    }
}
