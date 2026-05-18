<?php

declare(strict_types=1);

namespace Modular\Router;

use InvalidArgumentException;

final class RouteCompiler
{
    /**
     * @param list<RegisteredRoute> $registeredRoutes
     */
    public function compile(array $registeredRoutes): CompiledRouteTable
    {
        $modulePrefixes = [];
        $staticRoutes = [];
        $dynamicRoutes = [];

        foreach ($registeredRoutes as $registeredRoute) {
            $modulePrefixes[$registeredRoute->modulePrefix] = $registeredRoute->modulePrefix;
            $method = $registeredRoute->method->value;

            if ($registeredRoute->isDynamic === false) {
                $staticRoutes[$registeredRoute->modulePrefix][$method][$registeredRoute->relativePath] = $registeredRoute;

                continue;
            }

            $dynamicRoutes[$registeredRoute->modulePrefix][$method] ??= new DynamicTrieNode();
            $this->addDynamicRoute($dynamicRoutes[$registeredRoute->modulePrefix][$method], $registeredRoute);
        }

        $sortedModulePrefixes = array_values($modulePrefixes);

        usort(
            $sortedModulePrefixes,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
        );

        return new CompiledRouteTable($sortedModulePrefixes, $staticRoutes, $dynamicRoutes);
    }

    private function addDynamicRoute(DynamicTrieNode $rootNode, RegisteredRoute $registeredRoute): void
    {
        $node = $rootNode;

        foreach ($this->parseRouteSegments($registeredRoute->relativePath) as $segment) {
            if ($this->extractPlaceholderName($segment) !== null) {
                $node->placeholderChild ??= new DynamicTrieNode();
                $node = $node->placeholderChild;

                continue;
            }

            $node->staticChildren[$segment] ??= new DynamicTrieNode();
            $node = $node->staticChildren[$segment];
        }

        if ($node->route !== null) {
            throw new InvalidArgumentException(sprintf(
                'Ambiguous dynamic route registration for [%s] %s',
                $registeredRoute->method->value,
                $registeredRoute->path,
            ));
        }

        $node->route = $registeredRoute;
    }

    /**
     * @return list<string>
     */
    private function parseRouteSegments(string $path): array
    {
        if ($path === '/') {
            return [];
        }

        $segments = explode('/', ltrim($path, '/'));

        foreach ($segments as $segment) {
            if (str_contains($segment, '{') && $this->extractPlaceholderName($segment) === null) {
                throw new InvalidArgumentException(sprintf('Unsupported route path segment: %s', $segment));
            }
        }

        return $segments;
    }

    private function extractPlaceholderName(string $segment): ?string
    {
        if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
