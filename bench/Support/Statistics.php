<?php

declare(strict_types=1);

namespace Modular\Router\Bench\Support;

final class Statistics
{
    /**
     * @param list<int> $durations
     * @param list<int> $memoryDeltas
     * @return array<string,mixed>
     */
    public static function summarize(array $durations, array $memoryDeltas, int $operationsPerIteration): array
    {
        sort($durations);
        sort($memoryDeltas);

        $medianDuration = self::median($durations);
        $medianMemory = self::median($memoryDeltas);

        return [
            'iterations' => count($durations),
            'operations_per_iteration' => $operationsPerIteration,
            'duration_ns' => [
                'min' => $durations[0],
                'median' => $medianDuration,
                'max' => $durations[array_key_last($durations)],
                'mean' => (int) round(array_sum($durations) / count($durations)),
                'median_per_operation' => (int) round($medianDuration / max(1, $operationsPerIteration)),
            ],
            'memory_bytes' => [
                'min' => $memoryDeltas[0],
                'median' => $medianMemory,
                'max' => $memoryDeltas[array_key_last($memoryDeltas)],
                'mean' => (int) round(array_sum($memoryDeltas) / count($memoryDeltas)),
                'median_per_operation' => (int) round($medianMemory / max(1, $operationsPerIteration)),
            ],
        ];
    }

    /**
     * @param list<int> $values
     */
    private static function median(array $values): int
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
