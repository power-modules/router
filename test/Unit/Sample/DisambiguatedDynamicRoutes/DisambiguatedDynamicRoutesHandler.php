<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DisambiguatedDynamicRoutes;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DisambiguatedDynamicRoutesHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'attributes' => $request->getAttributes(),
        ]);
    }
}
