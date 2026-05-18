<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DispatchContract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AppendMiddlewareOrderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $current = $request->getHeader('X-Middleware-Order');
        $updated = [...$current, $this->name];

        return $handler->handle($request->withHeader('X-Middleware-Order', $updated));
    }
}
