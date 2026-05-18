<?php

declare(strict_types=1);

namespace Modular\Router;

final class RouteMatcher
{
    public function match(
        CompiledRouteTable $compiledRouteTable,
        string $modulePrefix,
        string $relativePath,
        RouteMethod $method,
    ): ?MatchedRoute {
        $staticRoute = $compiledRouteTable->staticRoutes[$modulePrefix][$method->value][$relativePath] ?? null;

        if ($staticRoute instanceof RegisteredRoute) {
            return new MatchedRoute($staticRoute, []);
        }

        $rootNode = $compiledRouteTable->dynamicRoutes[$modulePrefix][$method->value] ?? null;

        if (!$rootNode instanceof DynamicTrieNode) {
            return null;
        }

        return $this->matchDynamic($rootNode, $relativePath);
    }

    private function matchDynamic(DynamicTrieNode $rootNode, string $relativePath): ?MatchedRoute
    {
        $segments = $this->parseRequestSegments($relativePath);
        $capturedSegments = [];
        $node = $rootNode;

        foreach ($segments as $segment) {
            if (isset($node->staticChildren[$segment])) {
                $node = $node->staticChildren[$segment];

                continue;
            }

            if ($node->placeholderChild === null) {
                return null;
            }

            $capturedSegments[] = $segment;
            $node = $node->placeholderChild;
        }

        if (!$node->route instanceof RegisteredRoute) {
            return null;
        }

        return new MatchedRoute($node->route, $this->mapAttributes($node->route, $capturedSegments));
    }

    /**
     * @param list<string> $capturedSegments
     * @return array<string, string>
     */
    private function mapAttributes(RegisteredRoute $route, array $capturedSegments): array
    {
        $attributes = [];

        foreach ($route->placeholderNames as $index => $placeholderName) {
            if (!isset($capturedSegments[$index])) {
                break;
            }

            $attributes[$placeholderName] = $capturedSegments[$index];
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function parseRequestSegments(string $relativePath): array
    {
        if ($relativePath === '/') {
            return [];
        }

        return explode('/', ltrim($relativePath, '/'));
    }
}
