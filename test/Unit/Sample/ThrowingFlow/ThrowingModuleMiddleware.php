<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\ThrowingFlow;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class ThrowingModuleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() === '/throwing-flow/module-middleware') {
            throw new RuntimeException('Module middleware failure');
        }

        return $handler->handle($request);
    }
}
