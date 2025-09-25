<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Psr\Http\Message\ResponseInterface;

class BasicResponseDecorator
{
    public function __invoke(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('X-Library-A-Basic', 'true');
    }
}
