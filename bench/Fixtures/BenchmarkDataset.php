<?php

declare(strict_types=1);

namespace Modular\Router\Bench\Fixtures;

final readonly class BenchmarkDataset
{
    /**
     * @param list<SyntheticModule> $modules
     * @param array<string,list<array{method:string,uri:string}>> $requestSpecsByGroup
     */
    public function __construct(
        public string $name,
        public string $size,
        public int $routeCount,
        public array $modules,
        public array $requestSpecsByGroup,
    ) {
    }

    /**
     * @return array<string,int>
     */
    public function requestCounts(): array
    {
        $counts = [];

        foreach ($this->requestSpecsByGroup as $group => $requests) {
            $counts[$group] = count($requests);
        }

        return $counts;
    }
}
