<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\AmbiguousRoutes;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AmbiguousRoutesController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse($request->getAttributes());
    }
}
