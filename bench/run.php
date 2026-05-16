<?php

declare(strict_types=1);

use Modular\Router\Bench\Fixtures\BenchmarkDatasetFactory;
use Modular\Router\Bench\Support\BenchmarkHarness;

require __DIR__ . '/bootstrap.php';

$options = getopt('', [
    'dataset::',
    'size::',
    'iterations::',
    'warmup::',
    'revs::',
    'output::',
    'pretty',
    'list',
]);

if (array_key_exists('list', $options)) {
    $payload = [
        'datasets' => BenchmarkDatasetFactory::supportedDatasets(),
        'sizes' => BenchmarkDatasetFactory::supportedSizes(),
    ];

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

$datasetName = (string) ($options['dataset'] ?? 'shared-prefix-dynamic');
$size = (string) ($options['size'] ?? 'small');
$iterations = max(1, (int) ($options['iterations'] ?? 5));
$warmup = max(0, (int) ($options['warmup'] ?? 1));
$revs = max(1, (int) ($options['revs'] ?? 5));
$pretty = array_key_exists('pretty', $options);
$outputPath = isset($options['output']) ? (string) $options['output'] : null;

$dataset = BenchmarkDatasetFactory::create($datasetName, $size);
$harness = new BenchmarkHarness();
$payload = $harness->run($dataset, $iterations, $warmup, $revs);

$flags = JSON_THROW_ON_ERROR;

if ($pretty) {
    $flags |= JSON_PRETTY_PRINT;
}

$json = json_encode($payload, $flags) . PHP_EOL;

if ($outputPath !== null && $outputPath !== '') {
    $directory = dirname($outputPath);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($outputPath, $json);
}

echo $json;
