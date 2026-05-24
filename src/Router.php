<?php

declare(strict_types=1);

namespace Modular\Router;

use InvalidArgumentException;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasResponseDecorators;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class Router implements ModularRouterInterface
{
    /**
     * @var array<string, RegisteredRoute>
     */
    private array $registeredRouteIndex = [];

    private RouteGroupPrefixResolver $routeGroupPrefixResolver;

    private RouteCompiler $routeCompiler;

    private RouteMatcher $routeMatcher;

    private AllowedMethodsResolver $allowedMethodsResolver;

    private MiddlewarePipeline $middlewarePipeline;

    private ?CompiledRouteTable $compiledRouteTable = null;

    public function __construct(
        private readonly SyntheticResponseFactoryInterface $syntheticResponseFactory,
        private readonly ResponseDecoratorChainInterface $responseDecoratorChain,
    ) {
        $this->routeGroupPrefixResolver = new RouteGroupPrefixResolver();
        $this->routeCompiler = new RouteCompiler();
        $this->routeMatcher = new RouteMatcher();
        $this->allowedMethodsResolver = new AllowedMethodsResolver($this->routeMatcher);
        $this->middlewarePipeline = new MiddlewarePipeline();
    }

    public function addResponseDecorator(callable $decorator): ModularRouterInterface
    {
        $this->responseDecoratorChain->addResponseDecorator($decorator);

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

        $modulePrefix = $this->routeGroupPrefixResolver->getRouteGroupPrefix($powerModule);
        $moduleMiddleware = $powerModule instanceof HasMiddleware ? $powerModule->getMiddleware() : [];
        $moduleResponseDecorators = $powerModule instanceof HasResponseDecorators ? $powerModule->getResponseDecorators() : [];

        foreach ($powerModule->getRoutes() as $route) {
            $this->storeRegisteredRoute(
                $modulePrefix,
                $route,
                $moduleContainer,
                $moduleMiddleware,
                $moduleResponseDecorators,
            );
        }

        $this->compiledRouteTable = null;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->ensureCompiled();

        return $this->dispatch($request);
    }

    /**
     * @param list<class-string<MiddlewareInterface>> $moduleMiddleware
     * @param list<callable(ResponseInterface):ResponseInterface> $moduleResponseDecorators
     */
    private function storeRegisteredRoute(
        string $modulePrefix,
        Route $route,
        ContainerInterface $moduleContainer,
        array $moduleMiddleware,
        array $moduleResponseDecorators,
    ): void {
        $fullPath = $this->normalizeRoutePath($modulePrefix, $route->path);
        $relativePath = $this->normalizeRelativePath($modulePrefix, $fullPath);
        $placeholderNames = $this->extractPlaceholderNames($fullPath);
        $orderedMiddleware = [
            ...$moduleMiddleware,
            ...$route->getMiddleware(),
        ];
        $orderedResponseDecorators = [
            ...$moduleResponseDecorators,
            ...$route->getResponseDecorators(),
        ];
        $registeredRoute = new RegisteredRoute(
            modulePrefix: $modulePrefix,
            path: $fullPath,
            relativePath: $relativePath,
            method: $route->method,
            controllerName: $route->controllerName,
            moduleContainer: $moduleContainer,
            middleware: $route->getMiddleware(),
            responseDecorators: $route->getResponseDecorators(),
            placeholderNames: $placeholderNames,
            orderedMiddleware: $orderedMiddleware,
            orderedResponseDecorators: $orderedResponseDecorators,
            isDynamic: $placeholderNames !== [],
        );

        $indexKey = $this->getRegisteredRouteIndexKey($registeredRoute->method, $registeredRoute->path);

        if (isset($this->registeredRouteIndex[$indexKey])) {
            throw new InvalidArgumentException(sprintf(
                'Duplicate route registration for [%s] %s',
                $registeredRoute->method->value,
                $registeredRoute->path,
            ));
        }

        $this->registeredRouteIndex[$indexKey] = $registeredRoute;
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $compiledRouteTable = $this->compiledRouteTable;

        if ($compiledRouteTable === null) {
            throw new InvalidArgumentException('Compiled route table is not available.');
        }

        $requestPath = $this->normalizeRequestPath($request);
        $modulePrefix = $compiledRouteTable->resolveModulePrefix($requestPath);

        if ($modulePrefix === null) {
            return $this->responseDecoratorChain->decorateResponse(
                $this->syntheticResponseFactory->createNotFoundResponse($request),
            );
        }

        $relativePath = $compiledRouteTable->toRelativePath($modulePrefix, $requestPath);
        $requestMethod = strtoupper($request->getMethod());

        if ($requestMethod === RouteMethod::Options->value) {
            $explicitOptionsMatch = $this->routeMatcher->match(
                $compiledRouteTable,
                $modulePrefix,
                $relativePath,
                RouteMethod::Options,
            );

            if ($explicitOptionsMatch instanceof MatchedRoute) {
                return $this->executeMatchedRoute($request, $explicitOptionsMatch);
            }

            $allowedMethods = $this->allowedMethodsResolver->resolve($compiledRouteTable, $modulePrefix, $relativePath);

            return $allowedMethods === []
                ? $this->responseDecoratorChain->decorateResponse(
                    $this->syntheticResponseFactory->createNotFoundResponse($request),
                )
                : $this->responseDecoratorChain->decorateResponse(
                    $this->syntheticResponseFactory->createOptionsResponse($request, $allowedMethods),
                );
        }

        if ($requestMethod === RouteMethod::Head->value) {
            $explicitHeadMatch = $this->routeMatcher->match(
                $compiledRouteTable,
                $modulePrefix,
                $relativePath,
                RouteMethod::Head,
            );

            if ($explicitHeadMatch instanceof MatchedRoute) {
                return $this->executeMatchedRoute($request, $explicitHeadMatch);
            }

            $getMatch = $this->routeMatcher->match(
                $compiledRouteTable,
                $modulePrefix,
                $relativePath,
                RouteMethod::Get,
            );

            if ($getMatch instanceof MatchedRoute) {
                return $this->executeMatchedRoute($request->withMethod(RouteMethod::Get->value), $getMatch);
            }
        }

        $routeMethod = RouteMethod::tryFrom($requestMethod);

        if ($routeMethod instanceof RouteMethod) {
            $match = $this->routeMatcher->match(
                $compiledRouteTable,
                $modulePrefix,
                $relativePath,
                $routeMethod,
            );

            if ($match instanceof MatchedRoute) {
                return $this->executeMatchedRoute($request, $match);
            }
        }

        $allowedMethods = $this->allowedMethodsResolver->resolve($compiledRouteTable, $modulePrefix, $relativePath);

        if ($allowedMethods !== []) {
            return $this->responseDecoratorChain->decorateResponse(
                $this->syntheticResponseFactory->createMethodNotAllowedResponse($request, $allowedMethods),
            );
        }

        return $this->responseDecoratorChain->decorateResponse(
            $this->syntheticResponseFactory->createNotFoundResponse($request),
        );
    }

    private function executeMatchedRoute(ServerRequestInterface $request, MatchedRoute $matchedRoute): ResponseInterface
    {
        $response = $this->middlewarePipeline->handle($request, $matchedRoute);
        $response = $this->responseDecoratorChain->decorateResponse($response);

        foreach ($matchedRoute->route->orderedResponseDecorators as $responseDecorator) {
            $response = $responseDecorator($response);
        }

        return $response;
    }

    private function ensureCompiled(): void
    {
        if ($this->compiledRouteTable instanceof CompiledRouteTable) {
            return;
        }

        $this->compiledRouteTable = $this->routeCompiler->compile(array_values($this->registeredRouteIndex));
    }

    private function normalizeRoutePath(string $modulePrefix, string $routePath): string
    {
        if ($routePath === '' || $routePath === '/') {
            return $modulePrefix;
        }

        return rtrim($modulePrefix, '/') . '/' . ltrim($routePath, '/');
    }

    private function normalizeRelativePath(string $modulePrefix, string $fullPath): string
    {
        if ($fullPath === $modulePrefix) {
            return '/';
        }

        $relativePath = substr($fullPath, strlen($modulePrefix));

        if ($relativePath === '') {
            return '/';
        }

        return $relativePath;
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholderNames(string $path): array
    {
        if (preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches) !== 1 && empty($matches[1])) {
            return [];
        }

        return $matches[1];
    }

    private function getRegisteredRouteIndexKey(RouteMethod $method, string $path): string
    {
        return $method->value . ' ' . $path;
    }

    private function normalizeRequestPath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        if ($path === '') {
            return '/';
        }

        return $path;
    }
}
