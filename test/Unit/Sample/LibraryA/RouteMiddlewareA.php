<?php

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteMiddlewareA implements MiddlewareInterface
{
    public const ATTRIBUTE_FROM_MIDDLEWARE_VALUE = 'ATTRIBUTE_FROM_MIDDLEWARE_VALUE';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle(
            $request->withAttribute('attribute-from-middleware', self::ATTRIBUTE_FROM_MIDDLEWARE_VALUE),
        );
    }
}
