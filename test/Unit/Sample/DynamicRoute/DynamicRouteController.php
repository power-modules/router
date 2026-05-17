<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DynamicRoute;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DynamicRouteController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'id' => $request->getAttribute('id'),
        ]);
    }
}
