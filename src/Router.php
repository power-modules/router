<?php

declare(strict_types=1);

namespace Modular\Router;

use InvalidArgumentException;
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
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\ModularRouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class Router implements ModularRouterInterface
{
    private ConfigurableContainerInterface $container;
    private LeagueRouter $router;
    private RouteGroupPrefixResolver $routeGroupPrefixResolver;

    public function __construct(
        StrategyInterface $strategy,
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
            return;
        }

        $routeGroup = $this->router->group(
            $this->routeGroupPrefixResolver->getRouteGroupPrefix($powerModule),
            fn (RouteGroup $routeGroup) => $this->registerRoutes($routeGroup, $powerModule, $moduleContainer),
        );

        // Modules can implement HasMiddleware to add middleware to the entire route group
        if ($powerModule instanceof HasMiddleware) {
            $this->registerMiddlewares($routeGroup, $powerModule, $moduleContainer);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->handle($request);
    }

    private function registerRoutes(
        RouteGroup $routeGroup,
        HasRoutes $hasRoutes,
        ContainerInterface $moduleContainer,
    ): void {
        foreach ($hasRoutes->getRoutes() as $modularRoute) {
            $leagueRoute = $routeGroup->map(
                $modularRoute->method->value,
                $modularRoute->path,
                [
                    $modularRoute->controllerName,
                    $modularRoute->controllerMethodName,
                ],
            );

            $this->registerMiddlewares($leagueRoute, $modularRoute, $moduleContainer);

            /**
             * Register controller with the InstanceViaContainerResolver, so it can be resolved via the module container.
             *
             * Controllers are registered using their fully qualified class name as the key (e.g., App\User\UserController).
             * The InstanceViaContainerResolver ensures the controller is instantiated from its originating module's
             * container, maintaining proper module encapsulation and dependency resolution.
             *
             * Different namespaces prevent controller class conflicts naturally. If modules intentionally share
             * the same controller class (same fully qualified name), the last registration will be used,
             * which is typically acceptable for shared components.
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
        $middlewareAwareInterface->middlewares(
            array_map(
                fn (string $middlewareClassName): MiddlewareInterface => $this->getMiddleware($middlewareClassName, $moduleContainer),
                $hasMiddleware->getMiddleware(),
            ),
        );
    }

    /**
     * @param class-string<MiddlewareInterface> $middlewareClassName
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    private function getMiddleware(
        string $middlewareClassName,
        ContainerInterface $moduleContainer,
    ): MiddlewareInterface {
        if ($this->container->has($middlewareClassName) === true) {
            $middleware = $this->container->get($middlewareClassName);
        } elseif ($moduleContainer->has($middlewareClassName) === true) {
            $middleware = $moduleContainer->get($middlewareClassName);
        } else {
            throw new InvalidArgumentException(
                sprintf('Middleware %s not found in router or module container', $middlewareClassName),
            );
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(
                sprintf('Provided middleware is not an instance of %s (%s)', MiddlewareInterface::class, $middlewareClassName),
            );
        }

        return $middleware;
    }
}
