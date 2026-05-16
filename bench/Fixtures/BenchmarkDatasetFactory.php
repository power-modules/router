<?php

declare(strict_types=1);

namespace Modular\Router\Bench\Fixtures;

use InvalidArgumentException;
use Modular\Router\Route;

final class BenchmarkDatasetFactory
{
    /**
     * @var array<string,int>
     */
    private const MIXED_REQUEST_GROUP_WEIGHTS = [
        'hit' => 14,
        'not-found' => 3,
        'method-not-allowed' => 2,
        'head-fallback' => 1,
    ];

    /**
     * @var array<string,int>
     */
    private const SIZE_TO_ROUTE_TARGET = [
        'small' => 100,
        'medium' => 1000,
        'large' => 10000,
        'xlarge' => 50000,
    ];

    /**
     * @return list<string>
     */
    public static function supportedDatasets(): array
    {
        return [
            'shared-prefix-dynamic',
            'mixed-modules',
            'precedence',
            'constrained-placeholders',
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedSizes(): array
    {
        return array_keys(self::SIZE_TO_ROUTE_TARGET);
    }

    public static function create(string $datasetName, string $size): BenchmarkDataset
    {
        $routeTarget = self::SIZE_TO_ROUTE_TARGET[$size] ?? null;

        if ($routeTarget === null) {
            throw new InvalidArgumentException(sprintf('Unsupported size "%s"', $size));
        }

        return match ($datasetName) {
            'shared-prefix-dynamic' => self::buildSharedPrefixDynamic($size, $routeTarget),
            'mixed-modules' => self::buildMixedModules($size, $routeTarget),
            'precedence' => self::buildPrecedence($size, $routeTarget),
            'constrained-placeholders' => self::buildConstrainedPlaceholders($size, $routeTarget),
            default => throw new InvalidArgumentException(sprintf('Unsupported dataset "%s"', $datasetName)),
        };
    }

    private static function buildSharedPrefixDynamic(string $size, int $routeTarget): BenchmarkDataset
    {
        $routes = [];
        $hitRequests = [];
        $methodNotAllowedRequests = [];
        $headFallbackRequests = [];
        $notFoundRequests = [];

        for ($index = 1; $index <= $routeTarget; $index++) {
            $action = sprintf('action-%05d', $index);
            $path = '/post/{id}/' . $action;
            $uri = '/blog/post/' . $index . '/' . $action;

            $routes[] = Route::get($path, BenchmarkController::class);
            $hitRequests[] = ['method' => 'GET', 'uri' => $uri];
            $methodNotAllowedRequests[] = ['method' => 'POST', 'uri' => $uri];
            $headFallbackRequests[] = ['method' => 'HEAD', 'uri' => $uri];
            $notFoundRequests[] = ['method' => 'GET', 'uri' => '/blog/post/' . $index . '/missing-' . $action];
        }

        return new BenchmarkDataset(
            name: 'shared-prefix-dynamic',
            size: $size,
            routeCount: count($routes),
            modules: [new SyntheticModule('/blog', $routes)],
            requestSpecsByGroup: self::withMixedRequestGroup([
                'hit' => $hitRequests,
                'not-found' => self::sliceRequests($notFoundRequests),
                'method-not-allowed' => self::sliceRequests($methodNotAllowedRequests),
                'head-fallback' => self::sliceRequests($headFallbackRequests),
            ]),
        );
    }

    private static function buildMixedModules(string $size, int $routeTarget): BenchmarkDataset
    {
        $routesPerModule = 5;
        $moduleCount = max(1, (int) ceil($routeTarget / $routesPerModule));
        $modules = [];
        $hitRequests = [];
        $methodNotAllowedRequests = [];
        $headFallbackRequests = [];
        $notFoundRequests = [];
        $routeCount = 0;

        for ($moduleIndex = 1; $moduleIndex <= $moduleCount; $moduleIndex++) {
            $slug = sprintf('/module-%04d', $moduleIndex);
            $routes = [
                Route::get('/health', BenchmarkController::class),
                Route::get('/list', BenchmarkController::class),
                Route::get('/{id}', BenchmarkController::class),
                Route::put('/{id}/edit', BenchmarkController::class),
                Route::get('/{id}/items/{itemId}', BenchmarkController::class),
            ];

            $modules[] = new SyntheticModule($slug, $routes);
            $routeCount += count($routes);

            $entityId = (string) $moduleIndex;
            $itemId = (string) ($moduleIndex * 10);

            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/health'];
            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/list'];
            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/' . $entityId];
            $hitRequests[] = ['method' => 'PUT', 'uri' => $slug . '/' . $entityId . '/edit'];
            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/' . $entityId . '/items/' . $itemId];

            $methodNotAllowedRequests[] = ['method' => 'POST', 'uri' => $slug . '/health'];
            $methodNotAllowedRequests[] = ['method' => 'POST', 'uri' => $slug . '/' . $entityId . '/edit'];

            $headFallbackRequests[] = ['method' => 'HEAD', 'uri' => $slug . '/health'];
            $headFallbackRequests[] = ['method' => 'HEAD', 'uri' => $slug . '/' . $entityId];

            $notFoundRequests[] = ['method' => 'GET', 'uri' => $slug . '/missing/' . $entityId];
        }

        return new BenchmarkDataset(
            name: 'mixed-modules',
            size: $size,
            routeCount: $routeCount,
            modules: $modules,
            requestSpecsByGroup: self::withMixedRequestGroup([
                'hit' => self::sliceRequests($hitRequests),
                'not-found' => self::sliceRequests($notFoundRequests),
                'method-not-allowed' => self::sliceRequests($methodNotAllowedRequests),
                'head-fallback' => self::sliceRequests($headFallbackRequests),
            ]),
        );
    }

    private static function buildPrecedence(string $size, int $routeTarget): BenchmarkDataset
    {
        $routesPerModule = 4;
        $moduleCount = max(1, (int) ceil($routeTarget / $routesPerModule));
        $modules = [];
        $hitRequests = [];
        $methodNotAllowedRequests = [];
        $headFallbackRequests = [];
        $notFoundRequests = [];
        $routeCount = 0;

        for ($moduleIndex = 1; $moduleIndex <= $moduleCount; $moduleIndex++) {
            $slug = sprintf('/precedence-%04d', $moduleIndex);
            $routes = [
                Route::get('/users/me', BenchmarkController::class),
                Route::get('/users/{id}', BenchmarkController::class),
                Route::get('/teams/core/members', BenchmarkController::class),
                Route::get('/teams/{team}/members', BenchmarkController::class),
            ];

            $modules[] = new SyntheticModule($slug, $routes);
            $routeCount += count($routes);

            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/users/me'];
            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/users/' . $moduleIndex];
            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/teams/core/members'];
            $hitRequests[] = ['method' => 'GET', 'uri' => $slug . '/teams/team-' . $moduleIndex . '/members'];

            $methodNotAllowedRequests[] = ['method' => 'POST', 'uri' => $slug . '/users/me'];
            $methodNotAllowedRequests[] = ['method' => 'POST', 'uri' => $slug . '/teams/core/members'];

            $headFallbackRequests[] = ['method' => 'HEAD', 'uri' => $slug . '/users/me'];
            $headFallbackRequests[] = ['method' => 'HEAD', 'uri' => $slug . '/teams/team-' . $moduleIndex . '/members'];

            $notFoundRequests[] = ['method' => 'GET', 'uri' => $slug . '/users/missing/path'];
            $notFoundRequests[] = ['method' => 'GET', 'uri' => $slug . '/teams/unknown'];
        }

        return new BenchmarkDataset(
            name: 'precedence',
            size: $size,
            routeCount: $routeCount,
            modules: $modules,
            requestSpecsByGroup: self::withMixedRequestGroup([
                'hit' => self::sliceRequests($hitRequests),
                'not-found' => self::sliceRequests($notFoundRequests),
                'method-not-allowed' => self::sliceRequests($methodNotAllowedRequests),
                'head-fallback' => self::sliceRequests($headFallbackRequests),
            ]),
        );
    }

    private static function buildConstrainedPlaceholders(string $size, int $routeTarget): BenchmarkDataset
    {
        $routes = [];
        $hitRequests = [];
        $methodNotAllowedRequests = [];
        $headFallbackRequests = [];
        $notFoundRequests = [];

        for ($index = 1; $index <= $routeTarget; $index++) {
            $suffix = sprintf('%05d', $index);
            $routeVariant = ($index - 1) % 4;

            if ($routeVariant === 0) {
                $path = '/users/{id:number}/view-' . $suffix;
                $uri = '/api/v1/users/' . $index . '/view-' . $suffix;
                $invalidUri = '/api/v1/users/not-a-number/view-' . $suffix;
            } elseif ($routeVariant === 1) {
                $path = '/posts/{slug:slug}/detail-' . $suffix;
                $uri = '/api/v1/posts/post-' . $suffix . '/detail-' . $suffix;
                $invalidUri = '/api/v1/posts/Post_' . $suffix . '/detail-' . $suffix;
            } elseif ($routeVariant === 2) {
                $path = '/orders/{uuid:uuid}/trace-' . $suffix;
                $uri = '/api/v1/orders/' . self::buildUuidFromIndex($index) . '/trace-' . $suffix;
                $invalidUri = '/api/v1/orders/not-a-uuid/trace-' . $suffix;
            } else {
                $path = '/assets/{name:alphanum_dash}/download-' . $suffix;
                $uri = '/api/v1/assets/asset-' . $suffix . '/download-' . $suffix;
                $invalidUri = '/api/v1/assets/asset.' . $suffix . '/download-' . $suffix;
            }

            $routes[] = Route::get($path, BenchmarkController::class);
            $hitRequests[] = ['method' => 'GET', 'uri' => $uri];
            $methodNotAllowedRequests[] = ['method' => 'POST', 'uri' => $uri];
            $headFallbackRequests[] = ['method' => 'HEAD', 'uri' => $uri];
            $notFoundRequests[] = ['method' => 'GET', 'uri' => $invalidUri];
        }

        return new BenchmarkDataset(
            name: 'constrained-placeholders',
            size: $size,
            routeCount: count($routes),
            modules: [new SyntheticModule('/api/v1', $routes)],
            requestSpecsByGroup: self::withMixedRequestGroup([
                'hit' => self::sliceRequests($hitRequests),
                'not-found' => self::sliceRequests($notFoundRequests),
                'method-not-allowed' => self::sliceRequests($methodNotAllowedRequests),
                'head-fallback' => self::sliceRequests($headFallbackRequests),
            ]),
        );
    }

    /**
     * @param array<string,list<array{method:string,uri:string}>> $requestSpecsByGroup
     * @return array<string,list<array{method:string,uri:string}>>
     */
    private static function withMixedRequestGroup(array $requestSpecsByGroup): array
    {
        $mixedRequests = self::buildMixedRequests($requestSpecsByGroup);

        if ($mixedRequests === []) {
            return $requestSpecsByGroup;
        }

        $requestSpecsByGroup['mixed'] = $mixedRequests;

        return $requestSpecsByGroup;
    }

    /**
     * @param array<string,list<array{method:string,uri:string}>> $requestSpecsByGroup
     * @return list<array{method:string,uri:string}>
     */
    private static function buildMixedRequests(array $requestSpecsByGroup, int $maxRequests = 256): array
    {
        $queues = [];
        $offsets = [];

        foreach (self::MIXED_REQUEST_GROUP_WEIGHTS as $group => $_weight) {
            $queues[$group] = array_values($requestSpecsByGroup[$group] ?? []);
            $offsets[$group] = 0;
        }

        $mixedRequests = [];

        while (count($mixedRequests) < $maxRequests) {
            $addedInCycle = false;

            foreach (self::MIXED_REQUEST_GROUP_WEIGHTS as $group => $weight) {
                for ($count = 0; $count < $weight && count($mixedRequests) < $maxRequests; $count++) {
                    $offset = $offsets[$group];

                    if (!isset($queues[$group][$offset])) {
                        continue;
                    }

                    $mixedRequests[] = $queues[$group][$offset];
                    $offsets[$group]++;
                    $addedInCycle = true;
                }
            }

            if ($addedInCycle === false) {
                break;
            }
        }

        return $mixedRequests;
    }

    private static function buildUuidFromIndex(int $index): string
    {
        $hex = str_pad(dechex($index), 32, '0', STR_PAD_LEFT);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /**
     * @param list<array{method:string,uri:string}> $requests
     * @return list<array{method:string,uri:string}>
     */
    private static function sliceRequests(array $requests, int $maxRequests = 256): array
    {
        if (count($requests) <= $maxRequests) {
            return $requests;
        }

        return array_slice($requests, 0, $maxRequests);
    }
}
