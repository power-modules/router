<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DispatchContract;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DispatchContractController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new JsonResponse([
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'attributes' => $request->getAttributes(),
            'headers' => [
                'X-Middleware-Order' => $request->getHeader('X-Middleware-Order'),
            ],
        ]);

        $middlewareOrder = $request->getHeader('X-Middleware-Order');

        if ($middlewareOrder === []) {
            return $response;
        }

        return $response->withHeader('X-Middleware-Order', implode(',', $middlewareOrder));
    }
}
