<?php

declare(strict_types=1);

namespace Modular\Router;

final readonly class CompiledRouteTable
{
    /**
     * @param list<string> $modulePrefixes
     * @param array<string, array<string, array<string, RegisteredRoute>>> $staticRoutes
     * @param array<string, array<string, DynamicTrieNode>> $dynamicRoutes
     */
    public function __construct(
        public array $modulePrefixes,
        public array $staticRoutes,
        public array $dynamicRoutes,
    ) {
    }

    public function resolveModulePrefix(string $path): ?string
    {
        foreach ($this->modulePrefixes as $modulePrefix) {
            if ($modulePrefix === '/') {
                return '/';
            }

            if ($path === $modulePrefix || str_starts_with($path, $modulePrefix . '/')) {
                return $modulePrefix;
            }
        }

        return null;
    }

    public function toRelativePath(string $modulePrefix, string $path): string
    {
        if ($modulePrefix === '/') {
            return $path;
        }

        if ($path === $modulePrefix) {
            return '/';
        }

        $relativePath = substr($path, strlen($modulePrefix));

        if ($relativePath === '') {
            return '/';
        }

        return $relativePath;
    }
}
