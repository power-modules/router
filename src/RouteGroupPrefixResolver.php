<?php

declare(strict_types=1);

namespace Modular\Router;

use InvalidArgumentException;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasCustomRouteSlug;

class RouteGroupPrefixResolver
{
    /**
     * Groups routes by module name (without "Module" suffix), or by custom slug if the module implements HasCustomRouteSlug.
     * Normalizes the prefix to include a leading slash for clarity and consistency.
     * @throws InvalidArgumentException
     */
    public function getRouteGroupPrefix(PowerModule $powerModule): string
    {
        if ($powerModule instanceof HasCustomRouteSlug) {
            $customSlug = $powerModule->getRouteSlug();

            return $this->normalizeRoutePrefix($customSlug);
        }

        $className = $powerModule::class;
        $moduleName = preg_replace('/(?:.*\\\\)?([a-zA-Z0-9]+)Module$/', '$1', $className);

        if (is_string($moduleName) === false) {
            throw new InvalidArgumentException(
                sprintf('Unable to get module route group prefix: %s', $className),
            );
        }

        $moduleName = preg_replace('/(?<=[a-z])(?=[A-Z])/', '-', $moduleName);

        if (is_string($moduleName) === false) {
            throw new InvalidArgumentException(
                sprintf('Unable to convert module route group prefix: %s', $className),
            );
        }

        return $this->normalizeRoutePrefix(strtolower($moduleName));
    }

    /**
     * Ensures the route prefix has a leading slash for consistency.
     */
    private function normalizeRoutePrefix(string $prefix): string
    {
        return '/' . ltrim($prefix, '/');
    }
}
