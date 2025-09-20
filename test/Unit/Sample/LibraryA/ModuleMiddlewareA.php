<?php

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ModuleMiddlewareA implements MiddlewareInterface
{
    public const HEADER_FROM_MIDDLEWARE_VALUE = 'HEADER_FROM_MIDDLEWARE_VALUE';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle(
            $request->withHeader('header-from-middleware', self::HEADER_FROM_MIDDLEWARE_VALUE),
        );
    }
}
