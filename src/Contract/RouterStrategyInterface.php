<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

use Psr\Http\Message\ResponseInterface;

interface RouterStrategyInterface
{
    /**
     * @param callable(ResponseInterface):ResponseInterface $decorator
     */
    public function addResponseDecorator(callable $decorator): static;
}
