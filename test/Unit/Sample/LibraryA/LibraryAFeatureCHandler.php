<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\LibraryA;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LibraryAFeatureCHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'header-from-middleware' => $request->getHeader('header-from-middleware'),
        ]);
    }
}