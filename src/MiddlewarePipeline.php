<?php

declare(strict_types=1);

namespace Modular\Router;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipeline
{
    public function handle(
        ServerRequestInterface $request,
        MatchedRoute $matchedRoute,
    ): ResponseInterface {
        foreach ($matchedRoute->attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $handler = new class ($matchedRoute->route) implements RequestHandlerInterface {
            public function __construct(
                private readonly RegisteredRoute $route,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $resolvedHandler = $this->route->moduleContainer->get($this->route->controllerName);

                if (!$resolvedHandler instanceof RequestHandlerInterface) {
                    throw new InvalidArgumentException(sprintf(
                        'Resolved route handler must implement %s, %s returned.',
                        RequestHandlerInterface::class,
                        get_debug_type($resolvedHandler),
                    ));
                }

                return $resolvedHandler->handle($request);
            }
        };

        foreach (array_reverse($matchedRoute->route->orderedMiddleware) as $middlewareClassName) {
            $resolvedMiddleware = $matchedRoute->route->moduleContainer->get($middlewareClassName);

            if (!$resolvedMiddleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Resolved middleware must implement %s, %s returned.',
                    MiddlewareInterface::class,
                    get_debug_type($resolvedMiddleware),
                ));
            }

            $handler = new class ($resolvedMiddleware, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private readonly MiddlewareInterface $middleware,
                    private readonly RequestHandlerInterface $handler,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->handler);
                }
            };
        }

        return $handler->handle($request);
    }
}
