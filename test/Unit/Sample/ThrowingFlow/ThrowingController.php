<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\ThrowingFlow;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class ThrowingController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getUri()->getPath()) {
            '/throwing-flow/controller' => throw new RuntimeException('Controller failure'),
            '/throwing-flow/domain-exception' => throw new ThrowingDomainException('Domain failure'),
            default => new JsonResponse(['ok' => true]),
        };
    }
}
