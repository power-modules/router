<?php

declare(strict_types=1);

use Modular\Router\Bench\Fixtures\BenchmarkDatasetFactory;
use Modular\Router\Bench\Support\BenchmarkHarness;

require __DIR__ . '/bootstrap.php';

$options = getopt('', [
    'output::',
    'profile::',
    'label::',
    'pretty',
]);

$profiles = [
    'stable' => [
        'small' => ['iterations' => 5, 'warmup' => 2, 'revs' => 5],
        'medium' => ['iterations' => 5, 'warmup' => 2, 'revs' => 3],
        'large' => ['iterations' => 3, 'warmup' => 1, 'revs' => 2],
        'xlarge' => ['iterations' => 3, 'warmup' => 1, 'revs' => 1],
    ],
    'quick' => [
        'small' => ['iterations' => 2, 'warmup' => 1, 'revs' => 2],
        'medium' => ['iterations' => 1, 'warmup' => 0, 'revs' => 1],
        'large' => ['iterations' => 1, 'warmup' => 0, 'revs' => 1],
        'xlarge' => ['iterations' => 1, 'warmup' => 0, 'revs' => 1],
    ],
];

$profileName = (string) ($options['profile'] ?? 'stable');
$sizeParameters = $profiles[$profileName] ?? null;

if ($sizeParameters === null) {
    throw new RuntimeException(sprintf('Unsupported benchmark profile "%s"', $profileName));
}

$harness = new BenchmarkHarness();
$payload = [
    'meta' => [
        'generated_at_utc' => gmdate('c'),
        'profile' => $profileName,
        'label' => isset($options['label']) ? (string) $options['label'] : null,
        'size_parameters' => $sizeParameters,
        'environment' => BenchmarkHarness::environmentMetadata(),
    ],
    'results' => [],
];

foreach (BenchmarkDatasetFactory::supportedSizes() as $size) {
    $payload['results'][$size] = [];
    $parameters = $sizeParameters[$size] ?? null;

    if ($parameters === null) {
        throw new RuntimeException(sprintf('No benchmark parameters configured for size "%s"', $size));
    }

    foreach (BenchmarkDatasetFactory::supportedDatasets() as $datasetName) {
        $dataset = BenchmarkDatasetFactory::create($datasetName, $size);
        $result = $harness->run(
            $dataset,
            $parameters['iterations'],
            $parameters['warmup'],
            $parameters['revs'],
        );

        $payload['results'][$size][] = [
            'dataset' => $datasetName,
            'route_count' => $result['meta']['route_count'],
            'module_count' => $result['meta']['module_count'],
            'request_counts' => $result['meta']['request_counts'],
            'benchmark_parameters' => [
                'iterations' => $result['meta']['iterations'],
                'warmup' => $result['meta']['warmup'],
                'revs' => $result['meta']['revs'],
            ],
            'registration.duration_ns.median_per_operation' => $result['results']['registration']['duration_ns']['median_per_operation'],
            'registration.memory_bytes.median_per_operation' => $result['results']['registration']['memory_bytes']['median_per_operation'],
            'bootstrap-first-hit.duration_ns.median' => $result['results']['bootstrap-first-hit']['duration_ns']['median'],
            'bootstrap-first-hit.memory_bytes.median' => $result['results']['bootstrap-first-hit']['memory_bytes']['median'],
            'dispatch-hit.duration_ns.median_per_operation' => $result['results']['dispatch-hit']['duration_ns']['median_per_operation'],
            'dispatch-hit.memory_bytes.median_per_operation' => $result['results']['dispatch-hit']['memory_bytes']['median_per_operation'],
            'dispatch-not-found.duration_ns.median_per_operation' => $result['results']['dispatch-not-found']['duration_ns']['median_per_operation'],
            'dispatch-not-found.memory_bytes.median_per_operation' => $result['results']['dispatch-not-found']['memory_bytes']['median_per_operation'],
            'dispatch-method-not-allowed.duration_ns.median_per_operation' => $result['results']['dispatch-method-not-allowed']['duration_ns']['median_per_operation'],
            'dispatch-method-not-allowed.memory_bytes.median_per_operation' => $result['results']['dispatch-method-not-allowed']['memory_bytes']['median_per_operation'],
            'dispatch-head-fallback.duration_ns.median_per_operation' => $result['results']['dispatch-head-fallback']['duration_ns']['median_per_operation'],
            'dispatch-head-fallback.memory_bytes.median_per_operation' => $result['results']['dispatch-head-fallback']['memory_bytes']['median_per_operation'],
            'dispatch-mixed.duration_ns.median_per_operation' => $result['results']['dispatch-mixed']['duration_ns']['median_per_operation'],
            'dispatch-mixed.memory_bytes.median_per_operation' => $result['results']['dispatch-mixed']['memory_bytes']['median_per_operation'],
        ];
    }
}

$flags = JSON_THROW_ON_ERROR;

if (array_key_exists('pretty', $options)) {
    $flags |= JSON_PRETTY_PRINT;
}

$json = json_encode($payload, $flags) . PHP_EOL;
$outputPath = isset($options['output']) ? (string) $options['output'] : null;

if ($outputPath !== null && $outputPath !== '') {
    $directory = dirname($outputPath);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($outputPath, $json);
}

echo $json;
