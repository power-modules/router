<?php

declare(strict_types=1);

namespace Modular\Router\Bench\Support;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Router\Bench\Fixtures\BenchmarkDataset;
use Modular\Router\Router;
use Modular\Router\Strategy\JsonRouterStrategy;

final class BenchmarkHarness
{
    /**
     * @return array<string, bool|int|string|null>
     */
    public static function environmentMetadata(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os_family' => PHP_OS_FAMILY,
            'kernel' => sprintf('%s %s', php_uname('s'), php_uname('r')),
            'architecture' => php_uname('m'),
            'opcache_extension_loaded' => extension_loaded('Zend OPcache'),
            'opcache_enable_cli' => self::normalizeIniFlag(ini_get('opcache.enable_cli')),
            'opcache_jit' => self::normalizeIniValue(ini_get('opcache.jit')),
            'opcache_jit_buffer_size' => self::normalizeIniValue(ini_get('opcache.jit_buffer_size')),
            'git_commit' => self::readCommand('git rev-parse HEAD'),
            'git_branch' => self::readCommand('git rev-parse --abbrev-ref HEAD'),
            'git_is_dirty' => self::readCommand('git status --porcelain') !== null,
        ];
    }

    public function run(BenchmarkDataset $dataset, int $iterations, int $warmup, int $revs): array
    {
        $results = [
            'registration' => $this->benchmarkRegistration($dataset, $iterations, $warmup),
            'bootstrap-first-hit' => $this->benchmarkBootstrapFirstHit($dataset, $iterations, $warmup),
        ];

        foreach (array_keys($dataset->requestSpecsByGroup) as $group) {
            $results['dispatch-' . $group] = $this->benchmarkDispatch($dataset, $group, $iterations, $warmup, $revs);
        }

        return [
            'meta' => [
                'dataset' => $dataset->name,
                'size' => $dataset->size,
                'route_count' => $dataset->routeCount,
                'module_count' => count($dataset->modules),
                'request_counts' => $dataset->requestCounts(),
                'iterations' => $iterations,
                'warmup' => $warmup,
                'revs' => $revs,
                'environment' => self::environmentMetadata(),
            ],
            'results' => $results,
        ];
    }

    private function benchmarkBootstrapFirstHit(BenchmarkDataset $dataset, int $iterations, int $warmup): array
    {
        $request = $this->buildBootstrapRequest($dataset);

        for ($index = 0; $index < $warmup; $index++) {
            $router = $this->buildRegisteredRouter($dataset);
            $router->handle($request);
        }

        $durations = [];
        $memoryDeltas = [];

        for ($index = 0; $index < $iterations; $index++) {
            gc_collect_cycles();

            $startMemory = memory_get_usage(true);
            $start = hrtime(true);
            $router = $this->buildRegisteredRouter($dataset);
            $router->handle($request);
            $durations[] = hrtime(true) - $start;
            $memoryDeltas[] = max(0, memory_get_usage(true) - $startMemory);

            unset($router);
        }

        return Statistics::summarize($durations, $memoryDeltas, 1);
    }

    private function benchmarkRegistration(BenchmarkDataset $dataset, int $iterations, int $warmup): array
    {
        for ($index = 0; $index < $warmup; $index++) {
            $this->buildRegisteredRouter($dataset);
        }

        $durations = [];
        $memoryDeltas = [];

        for ($index = 0; $index < $iterations; $index++) {
            gc_collect_cycles();

            $startMemory = memory_get_usage(true);
            $start = hrtime(true);
            $router = $this->buildRegisteredRouter($dataset);
            $durations[] = hrtime(true) - $start;
            $memoryDeltas[] = max(0, memory_get_usage(true) - $startMemory);

            unset($router);
        }

        return Statistics::summarize($durations, $memoryDeltas, $dataset->routeCount);
    }

    private function benchmarkDispatch(
        BenchmarkDataset $dataset,
        string $group,
        int $iterations,
        int $warmup,
        int $revs,
    ): array {
        $router = $this->buildRegisteredRouter($dataset);
        $requests = $this->buildRequests($dataset->requestSpecsByGroup[$group]);

        if ($requests !== []) {
            $router->handle($requests[0]);
        }

        for ($index = 0; $index < $warmup; $index++) {
            $this->dispatchRequests($router, $requests, $revs);
        }

        $durations = [];
        $memoryDeltas = [];
        $operations = max(1, count($requests) * $revs);

        for ($index = 0; $index < $iterations; $index++) {
            gc_collect_cycles();

            $startMemory = memory_get_usage(true);
            $start = hrtime(true);
            $this->dispatchRequests($router, $requests, $revs);
            $durations[] = hrtime(true) - $start;
            $memoryDeltas[] = max(0, memory_get_usage(true) - $startMemory);
        }

        return Statistics::summarize($durations, $memoryDeltas, $operations);
    }

    private function buildRegisteredRouter(BenchmarkDataset $dataset): Router
    {
        $router = new Router(new JsonRouterStrategy(new ResponseFactory()));

        foreach ($dataset->modules as $module) {
            $moduleContainer = new ConfigurableContainer();
            $module->register($moduleContainer);
            $router->registerPowerModuleRoutes($module, $moduleContainer);
        }

        return $router;
    }

    /**
     * @param list<array{method:string,uri:string}> $requestSpecs
     * @return list<\Psr\Http\Message\ServerRequestInterface>
     */
    private function buildRequests(array $requestSpecs): array
    {
        $requestFactory = new ServerRequestFactory();
        $requests = [];

        foreach ($requestSpecs as $requestSpec) {
            $requests[] = $requestFactory->createServerRequest($requestSpec['method'], $requestSpec['uri']);
        }

        return $requests;
    }

    private function buildBootstrapRequest(BenchmarkDataset $dataset): \Psr\Http\Message\ServerRequestInterface
    {
        $requestFactory = new ServerRequestFactory();
        $requestSpec = $dataset->requestSpecsByGroup['hit'][0] ?? ['method' => 'GET', 'uri' => '/'];

        return $requestFactory->createServerRequest($requestSpec['method'], $requestSpec['uri']);
    }

    /**
     * @param list<\Psr\Http\Message\ServerRequestInterface> $requests
     */
    private function dispatchRequests(Router $router, array $requests, int $revs): void
    {
        for ($rev = 0; $rev < $revs; $rev++) {
            foreach ($requests as $request) {
                $router->handle($request);
            }
        }
    }

    private static function normalizeIniFlag(string|false $value): bool|int|string|null
    {
        if ($value === false || $value === '') {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? self::normalizeIniValue($value);
    }

    private static function normalizeIniValue(string|false $value): string|int|null
    {
        if ($value === false || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    private static function readCommand(string $command): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $output = shell_exec($command . ' 2>/dev/null');

        if (!is_string($output)) {
            return null;
        }

        $trimmedOutput = trim($output);

        return $trimmedOutput === '' ? null : $trimmedOutput;
    }
}
