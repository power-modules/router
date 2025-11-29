<?php

declare(strict_types=1);

namespace Modular\Router;

use BackedEnum;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

final class RouteBuilder
{
    private ?string $controllerName = null;

    private string $methodName = 'index';

    private RouteMethod $routeMethod = RouteMethod::Get;

    /**
     * @var array<string|BackedEnum>
     */
    private array $pathSegments = [];

    /**
     * @var array<class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    public static function for(string $controllerName, string $methodName = '__invoke'): self
    {
        $instance = new self();
        $instance->controllerName = $controllerName;
        $instance->methodName = $methodName;

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
        $this->middleware = array_merge($this->middleware, $middlewares);

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
            RouteMethod::Get => Route::get($path, $this->controllerName, $this->methodName),
            RouteMethod::Post => Route::post($path, $this->controllerName, $this->methodName),
            RouteMethod::Put => Route::put($path, $this->controllerName, $this->methodName),
            RouteMethod::Delete => Route::delete($path, $this->controllerName, $this->methodName),
            RouteMethod::Patch => Route::patch($path, $this->controllerName, $this->methodName),
            RouteMethod::Options => Route::options($path, $this->controllerName, $this->methodName),
            RouteMethod::Head => Route::head($path, $this->controllerName, $this->methodName),
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
