<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\ThrowingFlow;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class ThrowingRouteMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new RuntimeException('Route middleware failure');
    }
}
