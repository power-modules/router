<?php

declare(strict_types=1);

use Modular\Router\Bench\Fixtures\BenchmarkDatasetFactory;
use Modular\Router\Bench\Support\BenchmarkHarness;

require __DIR__ . '/bootstrap.php';

$options = getopt('', [
    'output::',
    'pretty',
]);

$sizeParameters = [
    'small' => ['iterations' => 3, 'warmup' => 1, 'revs' => 3],
    'medium' => ['iterations' => 2, 'warmup' => 1, 'revs' => 2],
    'large' => ['iterations' => 1, 'warmup' => 0, 'revs' => 1],
    'xlarge' => ['iterations' => 1, 'warmup' => 0, 'revs' => 1],
];

$harness = new BenchmarkHarness();
$payload = [];

foreach (BenchmarkDatasetFactory::supportedSizes() as $size) {
    $payload[$size] = [];
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

        $payload[$size][] = [
            'dataset' => $datasetName,
            'route_count' => $result['meta']['route_count'],
            'registration.duration_ns.median_per_operation' => $result['results']['registration']['duration_ns']['median_per_operation'],
            'bootstrap-first-hit.duration_ns.median' => $result['results']['bootstrap-first-hit']['duration_ns']['median'],
            'dispatch-hit.duration_ns.median_per_operation' => $result['results']['dispatch-hit']['duration_ns']['median_per_operation'],
            'dispatch-not-found.duration_ns.median_per_operation' => $result['results']['dispatch-not-found']['duration_ns']['median_per_operation'],
            'dispatch-method-not-allowed.duration_ns.median_per_operation' => $result['results']['dispatch-method-not-allowed']['duration_ns']['median_per_operation'],
            'dispatch-head-fallback.duration_ns.median_per_operation' => $result['results']['dispatch-head-fallback']['duration_ns']['median_per_operation'],
            'dispatch-mixed.duration_ns.median_per_operation' => $result['results']['dispatch-mixed']['duration_ns']['median_per_operation'],
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
