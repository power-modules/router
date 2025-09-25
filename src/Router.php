<?php

declare(strict_types=1);

namespace Modular\Router;

use League\Route\ContainerAwareInterface;
use League\Route\Middleware\MiddlewareAwareInterface;
use League\Route\RouteGroup;
use League\Route\Router as LeagueRouter;
use League\Route\Strategy\StrategyInterface;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\Container\InstanceResolver\InstanceViaContainerResolver;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasResponseDecorators;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\ModularRouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router implements ModularRouterInterface
{
    private ConfigurableContainerInterface $container;
    private LeagueRouter $router;
    private RouteGroupPrefixResolver $routeGroupPrefixResolver;

    public function __construct(
        private readonly StrategyInterface $strategy, // This strategy will be used as a prototype for each module's route group
    ) {
        $this->container = new ConfigurableContainer();
        $this->router = new LeagueRouter();
        $this->routeGroupPrefixResolver = new RouteGroupPrefixResolver();

        if ($strategy instanceof ContainerAwareInterface) {
            $strategy->setContainer($this->container);
        }

        $this->router->setStrategy($strategy);
    }

    public function addResponseDecorator(callable $decorator): ModularRouterInterface
    {
        $this->router->getStrategy()?->addResponseDecorator($decorator);

        return $this;
    }

    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer,
    ): void {
        if (!$powerModule instanceof HasRoutes) {
            // Check only for HasRoutes here; HasResponseDecorators makes sense only in combination with routes
            return;
        }

        $moduleRouteGroup = $this->router->group(
            $this->routeGroupPrefixResolver->getRouteGroupPrefix($powerModule),
            fn (RouteGroup $routeGroup) => $this->registerRoutes($routeGroup, $powerModule, $moduleContainer),
        );

        // Modules can implement HasMiddleware to add middleware to the entire route group
        if ($powerModule instanceof HasMiddleware) {
            $this->registerMiddlewares($moduleRouteGroup, $powerModule, $moduleContainer);
        }

        // Modules can implement HasResponseDecorators to add response decorators to the entire route group (e.g. /library-a/**)
        if ($powerModule instanceof HasResponseDecorators) {
            // Clone the strategy to avoid affecting other modules
            $moduleRouteGroupStrategy = clone $this->strategy;
            $moduleRouteGroup->setStrategy($moduleRouteGroupStrategy);

            $this->registerResponseDecorators($moduleRouteGroupStrategy, $powerModule);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->handle($request);
    }

    private function registerRoutes(
        RouteGroup $moduleRouteGroup,
        HasRoutes $hasRoutes,
        ContainerInterface $moduleContainer,
    ): void {
        foreach ($hasRoutes->getRoutes() as $modularRoute) {
            $leagueRoute = $moduleRouteGroup->map(
                $modularRoute->method->value,
                $modularRoute->path,
                [
                    $modularRoute->controllerName,
                    $modularRoute->controllerMethodName,
                ],
            );

            $this->registerMiddlewares($leagueRoute, $modularRoute, $moduleContainer);

            /**
             * League Route's Strategy inherits its group strategy, making a single route decorator applicable for all routes, which is not desired.
             * Therefore, we need to clone the original route strategy (which is essentially the group strategy) for each route to isolate decorators.
             * The group strategy is only set if the module implements HasResponseDecorators.
             *
             * @see \League\Route\RouteGroup line 68
             */
            $routeStrategy = clone ($leagueRoute->getStrategy() ?? $this->strategy);
            $leagueRoute->setStrategy($routeStrategy);
            $this->registerResponseDecorators($routeStrategy, $modularRoute);

            /**
             * Register controller with the InstanceViaContainerResolver, so it can be resolved via the module container.
             *
             * Controllers are registered using their fully qualified class name as the key (e.g., App\User\UserController).
             * The InstanceViaContainerResolver ensures the controller is instantiated from its originating module's
             * container, maintaining proper module encapsulation and dependency resolution.
             *
             * Different namespaces prevent controller class conflicts naturally. If modules intentionally share
             * the same controller class (same fully qualified name), the last registration will be used,
             * which is typically acceptable for shared components, just make sure it has all required dependencies.
             *
             * @see \Modular\Router\Route
             * @see \Modular\Framework\Container\InstanceResolver\InstanceViaContainerResolver
             */
            $this->container->set(
                $modularRoute->controllerName,
                $moduleContainer,
                InstanceViaContainerResolver::class,
            );
        }
    }

    /**
     * Registers middlewares for a route, route group or module.
     */
    private function registerMiddlewares(
        MiddlewareAwareInterface $middlewareAwareInterface,
        HasMiddleware $hasMiddleware,
        ContainerInterface $moduleContainer,
    ): void {
        foreach ($hasMiddleware->getMiddleware() as $middlewareClassName) {
            // The \League\Route\Dispatcher is able to resolve middleware lazily, if the strategy is container aware
            // All we need to do is register the class names in the root container with reference to the module container
            $this->container->set($middlewareClassName, $moduleContainer, InstanceViaContainerResolver::class);
            $middlewareAwareInterface->lazyMiddleware($middlewareClassName);
        }
    }

    private function registerResponseDecorators(
        StrategyInterface $strategy,
        HasResponseDecorators $hasResponseDecorators,
    ): void {
        foreach ($hasResponseDecorators->getResponseDecorators() as $responseDecorator) {
            $strategy->addResponseDecorator($responseDecorator);
        }
    }
}
