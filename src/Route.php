<?php

declare(strict_types=1);

namespace Modular\Router;

use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasResponseDecorators;
use Psr\Http\Message\ResponseInterface;
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
class Route implements HasMiddleware, HasResponseDecorators
{
    /**
     * @var list<class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    /**
     * @var list<callable(ResponseInterface):ResponseInterface>
     */
    private array $responseDecorators = [];

    /**
     * @param class-string $controllerName
     */
    public function __construct(
        public readonly string $path,
        public readonly string $controllerName,
        public readonly RouteMethod $method = RouteMethod::Get,
    ) {
    }

    /**
     * @param class-string $controllerName
     */
    public static function get(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Get);
    }

    /**
     * @param class-string $controllerName
     */
    public static function post(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Post);
    }

    /**
     * @param class-string $controllerName
     */
    public static function put(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Put);
    }

    /**
     * @param class-string $controllerName
     */
    public static function delete(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Delete);
    }

    /**
     * @param class-string $controllerName
     */
    public static function patch(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Patch);
    }

    /**
     * @param class-string $controllerName
     */
    public static function options(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Options);
    }

    /**
     * @param class-string $controllerName
     */
    public static function head(string $path, string $controllerName): self
    {
        return new self($path, $controllerName, RouteMethod::Head);
    }

    /**
     * @param class-string<MiddlewareInterface> ...$middlewareClassNames
     */
    public function addMiddleware(string ...$middlewareClassNames): self
    {
        foreach ($middlewareClassNames as $middlewareClassName) {
            $this->middleware = [
                ...$this->middleware,
                $middlewareClassName,
            ];
        }

        return $this;
    }

    /**
     * @return list<class-string<MiddlewareInterface>>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @param callable(ResponseInterface):ResponseInterface $responseDecorator
     */
    public function addResponseDecorator(callable $responseDecorator): self
    {
        $this->responseDecorators[] = $responseDecorator;

        return $this;
    }

    /**
     * @return list<callable(ResponseInterface):ResponseInterface>
     */
    public function getResponseDecorators(): array
    {
        return $this->responseDecorators;
    }
}
