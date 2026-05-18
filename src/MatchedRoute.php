<?php

declare(strict_types=1);

namespace Modular\Router;

final readonly class MatchedRoute
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public RegisteredRoute $route,
        public array $attributes,
    ) {
    }
}
