<?php

declare(strict_types=1);

namespace Modular\Router;

use Modular\Router\Contract\HttpEntrypointInterface;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpEntrypoint implements HttpEntrypointInterface
{
    public function __construct(
        private readonly HttpEntrypointMiddlewareInterface $httpEntrypointMiddleware,
        private readonly RequestHandlerInterface $router,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->httpEntrypointMiddleware->process($request, $this->router);
    }
}
