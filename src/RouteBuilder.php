<?php

declare(strict_types=1);

namespace Modular\Router;

use BackedEnum;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

final class RouteBuilder
{
    /**
     * @var class-string|null
     */
    private ?string $controllerName = null;

    private RouteMethod $routeMethod = RouteMethod::Get;

    /**
     * @var array<string|BackedEnum>
     */
    private array $pathSegments = [];

    /**
     * @var list<class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    /**
     * @param class-string $controllerName
     */
    public static function for(string $controllerName): self
    {
        $instance = new self();
        $instance->controllerName = $controllerName;

        return $instance;
    }

    public function withMethod(RouteMethod $method): self
    {
        $this->routeMethod = $method;

        return $this;
    }

    /**
     * @param class-string<MiddlewareInterface> ...$middlewares
     */
    public function withMiddleware(string ...$middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * @param string|BackedEnum|array<string|BackedEnum> ...$pathSegments - Can be strings, enum case, or all enum cases
     */
    public function addPath(string|BackedEnum|array ...$pathSegments): self
    {
        foreach ($pathSegments as $segment) {
            if (is_array($segment) === true) {
                // expand enum cases
                foreach ($segment as $subSegment) {
                    $this->pathSegments[] = $subSegment;
                }

                continue;
            }

            $this->pathSegments[] = $segment;
        }

        return $this;
    }

    public function build(): Route
    {
        if ($this->controllerName === null) {
            throw new RuntimeException('Controller name must be set using for() method');
        }

        $path = $this->buildPath();

        $route = match ($this->routeMethod) {
            RouteMethod::Get => Route::get($path, $this->controllerName),
            RouteMethod::Post => Route::post($path, $this->controllerName),
            RouteMethod::Put => Route::put($path, $this->controllerName),
            RouteMethod::Delete => Route::delete($path, $this->controllerName),
            RouteMethod::Patch => Route::patch($path, $this->controllerName),
            RouteMethod::Options => Route::options($path, $this->controllerName),
            RouteMethod::Head => Route::head($path, $this->controllerName),
        };

        foreach ($this->middleware as $middlewareClass) {
            $route->addMiddleware($middlewareClass);
        }

        return $route;
    }

    private function buildPath(): string
    {
        if (count($this->pathSegments) === 0) {
            return '/';
        }

        $path = '';

        foreach ($this->pathSegments as $segment) {
            $path .= $this->interpolateSegment($segment);
        }

        return $path;
    }

    private function interpolateSegment(string|BackedEnum $segment): string
    {
        return '/' . (is_string($segment) ? $segment : '{' . $segment->value . '}');
    }
}
