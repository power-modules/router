<?php

declare(strict_types=1);

namespace Modular\Router;

use Modular\Router\Contract\HasMiddleware;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Represents a route definition within the application.
 *
 * The explicit inclusion of the "controllerName" property allows the Router to register
 * controllers using a reference to the controller's module-specific dependency injection (DI) container.
 * This design ensures that the Router remains decoupled from the controller's internal dependencies,
 * delegating their resolution to the appropriate module container. As a result, the Router does not
 * need to be aware of the controller's construction details, promoting modularity and separation of concerns.
 *
 * For enhanced compatibility, the specified controller name may implement the PSR-15 RequestHandlerInterface.
 */
class Route implements HasMiddleware
{
    /**
     * @var array<class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    public function __construct(
        public readonly string $path,
        public readonly string $controllerName,
        public readonly string $controllerMethodName,
        public readonly RouteMethod $method = RouteMethod::Get,
    ) {
    }

    public static function get(string $path, string $controllerName, string $controllerMethodName = 'handle'): self
    {
        return new self($path, $controllerName, $controllerMethodName, RouteMethod::Get);
    }

    public static function post(string $path, string $controllerName, string $controllerMethodName = 'handle'): self
    {
        return new self($path, $controllerName, $controllerMethodName, RouteMethod::Post);
    }

    public static function put(string $path, string $controllerName, string $controllerMethodName = 'handle'): self
    {
        return new self($path, $controllerName, $controllerMethodName, RouteMethod::Put);
    }

    public static function delete(string $path, string $controllerName, string $controllerMethodName = 'handle'): self
    {
        return new self($path, $controllerName, $controllerMethodName, RouteMethod::Delete);
    }

    public static function patch(string $path, string $controllerName, string $controllerMethodName = 'handle'): self
    {
        return new self($path, $controllerName, $controllerMethodName, RouteMethod::Patch);
    }

    public function addMiddleware(string ...$middlewareClassNames): self
    {
        foreach ($middlewareClassNames as $middlewareClassName) {
            if (is_a($middlewareClassName, MiddlewareInterface::class, true) === false) {
                continue;
            }

            $this->middleware = [
                ...$this->middleware,
                $middlewareClassName,
            ];
        }

        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
