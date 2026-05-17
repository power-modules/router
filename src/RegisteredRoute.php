<?php

declare(strict_types=1);

namespace Modular\Router;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

final readonly class RegisteredRoute
{
    /** @var list<class-string<MiddlewareInterface>> */
    public array $middleware;

    /** @var list<callable(ResponseInterface):ResponseInterface> */
    public array $responseDecorators;

    /** @var list<string> */
    public array $placeholderNames;

    /**
     * @param class-string $controllerName
     * @param list<class-string<MiddlewareInterface>> $middleware
     * @param list<callable(ResponseInterface):ResponseInterface> $responseDecorators
     * @param list<string> $placeholderNames
     */
    public function __construct(
        public string $modulePrefix,
        public string $path,
        public RouteMethod $method,
        public string $controllerName,
        public string $controllerMethodName,
        public ContainerInterface $moduleContainer,
        array $middleware,
        array $responseDecorators,
        array $placeholderNames,
    ) {
        $this->middleware = $middleware;
        $this->responseDecorators = $responseDecorators;
        $this->placeholderNames = $placeholderNames;
    }
}
