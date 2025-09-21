<?php

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LibraryAController implements RequestHandlerInterface
{
    public const array HANDLE_RESPONSE = [
        'data' => 'Modular Framework is awesome!',
    ];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(self::HANDLE_RESPONSE);
    }

    public function featureB(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'attribute-from-middleware' => $request->getAttribute('attribute-from-middleware'),
        ]);
    }

    public function featureC(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'header-from-middleware' => $request->getHeader('header-from-middleware'),
        ]);
    }
}
