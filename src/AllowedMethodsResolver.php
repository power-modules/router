<?php

declare(strict_types=1);

namespace Modular\Router;

final class AllowedMethodsResolver
{
    /**
     * @var list<RouteMethod>
     */
    private const MATCHABLE_METHODS = [
        RouteMethod::Get,
        RouteMethod::Head,
        RouteMethod::Options,
        RouteMethod::Post,
        RouteMethod::Put,
        RouteMethod::Patch,
        RouteMethod::Delete,
    ];

    public function __construct(
        private readonly RouteMatcher $routeMatcher,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolve(
        CompiledRouteTable $compiledRouteTable,
        string $modulePrefix,
        string $relativePath,
    ): array {
        $allowedMethods = [];

        foreach (self::MATCHABLE_METHODS as $routeMethod) {
            if ($this->routeMatcher->match($compiledRouteTable, $modulePrefix, $relativePath, $routeMethod) === null) {
                continue;
            }

            $allowedMethods[$routeMethod->value] = $routeMethod->value;
        }

        if (isset($allowedMethods[RouteMethod::Get->value])) {
            $allowedMethods[RouteMethod::Head->value] = RouteMethod::Head->value;
        }

        if ($allowedMethods !== []) {
            $allowedMethods[RouteMethod::Options->value] = RouteMethod::Options->value;
        }

        $orderedAllowedMethods = [];

        foreach (self::MATCHABLE_METHODS as $routeMethod) {
            if (isset($allowedMethods[$routeMethod->value])) {
                $orderedAllowedMethods[] = $routeMethod->value;
            }
        }

        return $orderedAllowedMethods;
    }
}
