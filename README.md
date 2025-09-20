# Power Modules Router

[![CI](https://github.com/power-modules/router/actions/workflows/php.yml/badge.svg)](https://github.com/power-modules/router/actions/workflows/php.yml)
[![Packagist Version](https://img.shields.io/packagist/v/power-modules/router)](https://packagist.org/packages/power-modules/router)
[![PHP Version](https://img.shields.io/packagist/php-v/power-modules/router)](https://packagist.org/packages/power-modules/router)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-blue)](#)

A modular router component for the Power Modules framework that brings true module encapsulation to HTTP routing. Each module defines its own routes and middleware while controllers and middleware are resolved from their module-specific DI containers.

## Stability

This project is early production-ready. It’s designed and tested for small-to-medium applications; broader-scale validation is ongoing. Interfaces may evolve with feedback.

## 🚀 Key Innovations

- 🎯 Module-Centric Routing: automatic route grouping and prefixing per module (kebab-case from module name)
- 🔒 True Encapsulation: controllers and middleware resolve from the originating module’s DI container
- ⚙️ Zero-Config Module Setup: implement `HasRoutes` and the framework wires everything during setup
- 🛡️ PSR-Friendly: PSR-7/15/17 aligned, built on League/Route
- 🧰 Response Decorators: add global response transformations via strategy decorators

## 🎯 Perfect For

- 🏢 Modular Monoliths: maintain clear routing boundaries as applications grow
- 🧩 Plugin Architectures: let modules ship self-contained routes and middleware
- 🚀 APIs: clean separation of concerns with module-owned endpoints
- 👥 Teams: independent development within isolated module containers

## How It Works

The router applies the framework’s module encapsulation principles to routing:

- Automatic route discovery: modules implementing `HasRoutes` are detected during application setup
- Route grouping and prefixing:
  - Default: module class name (without “Module” suffix) is converted to kebab-case
  - Example: `UserManagementModule` → `/user-management`
  - Custom: implement `HasCustomRouteSlug` to return a custom leading-slash prefix (recommended no trailing slash)
- Dependency resolution:
  - Controllers are registered with a container-aware instance resolver so they resolve from their module container
  - Middleware resolution precedence: router container → module container → new instance (validated to implement PSR-15)

Under the hood, the router wraps League/Route and configures a Strategy (default: `ApplicationStrategy`) which you can override via configuration.

## Installation

```sh
composer require power-modules/router
```

## Requirements

- PHP: 8.4+
- Framework: [power-modules/framework](https://github.com/power-modules/framework) ^1.0
- Router engine: [league/route](https://route.thephpleague.com/) ^6.2

### Optional Dependencies

Pick a PSR-7 implementation and emitter for HTTP I/O:

- PSR-7: laminas/laminas-diactoros (used in examples/tests)
- Emitter: laminas/laminas-httphandlerrunner (SAPI emitter)

```sh
composer require laminas/laminas-diactoros laminas/laminas-httphandlerrunner
```

## 📚 Documentation

- [Quick Start](docs/quick-start.md) — wire the router into a Power Modules app in minutes

## Quick Start

Install:
```sh
composer require power-modules/router
```

Minimal bootstrap outline:
```php
$app = new ModularAppBuilder(__DIR__)->build();
$app->addPowerModuleSetup(new RoutingSetup());
$app->registerModules([RouterModule::class, YourModule::class]); // YourModule implements HasRoutes
$router = $app->get(ModularRouterInterface::class);
$response = $router->handle($request);
```

For the complete runnable example (controllers, modules, and emitter), see the Quick Start guide: [docs/quick-start.md](docs/quick-start.md).

## Usage

- Define routes in a module by implementing `HasRoutes` and returning `Route` instances
- Use module-level middleware with `HasMiddleware`
- Override the module prefix with `HasCustomRouteSlug` (return a leading slash, e.g. `/api/v1`)

```php
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\HasMiddleware;
use Modular\Router\Contract\HasCustomRouteSlug;
use Modular\Router\Route;

final readonly class ApiModule implements PowerModule, HasRoutes, HasMiddleware, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/api/v1'; // leading slash; avoid trailing slash to prevent double slashes
    }

    public function getMiddleware(): array
    {
        return [AuthMiddleware::class, LoggingMiddleware::class];
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/status', StatusController::class),               // /api/v1/status
            Route::post('/data', DataController::class, 'store')          // /api/v1/data
                ->addMiddleware(ValidationMiddleware::class),
        ];
    }
}
```

## API Reference

### Route definition

```php
Route::get('/path', Controller::class, 'method');   // default method is 'handle'
Route::post('/path', Controller::class, 'method');
Route::put('/path', Controller::class, 'method');
Route::patch('/path', Controller::class, 'method');
Route::delete('/path', Controller::class, 'method');
```

- Controller method default: `handle()` (PSR-15 `RequestHandlerInterface`)
- Middleware per-route:
  ```php
  Route::get('/protected', ProtectedController::class)
      ->addMiddleware(AuthMiddleware::class, ThrottleMiddleware::class);
  ```

### Module contracts

- `HasRoutes`: define module routes
- `HasMiddleware`: add module-wide middleware
- `HasCustomRouteSlug`: override automatic prefix (return leading-slash, recommended no trailing slash)

### Router interface

```php
interface ModularRouterInterface extends Psr\Http\Server\RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        Modular\Framework\PowerModule\Contract\PowerModule $powerModule,
        Psr\Container\ContainerInterface $moduleContainer,
        ?Modular\Framework\Config\Contract\PowerModuleConfig $powerModuleConfig,
    ): void;

    public function addResponseDecorator(callable $decorator): ModularRouterInterface;
}
```

### Response decorators

```php
$router->addResponseDecorator(
    function (Psr\Http\Message\ResponseInterface $response): Psr\Http\Message\ResponseInterface {
        return $response->withHeader('X-App', 'PowerModules');
    }
);
```

## Configuration

The router exposes a module config with a default League/Route strategy:

- Config file name: `modular_router.php` (picked up by the framework)
- Default: `ApplicationStrategy`
- Override example (JSON responses):

```php
<?php

declare(strict_types=1);

use Laminas\Diactoros\ResponseFactory;
use League\Route\Strategy\JsonStrategy;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;

return Config::create()
    ->set(Setting::Strategy, new JsonStrategy(new ResponseFactory()));
```

## Route Prefixing

- Automatic prefix: kebab-case from module class name without “Module” suffix
  - `UserManagementModule` → `/user-management`
- Custom prefix: implement `HasCustomRouteSlug` and return a leading-slash value
  - Recommended: no trailing slash to avoid `//` when mapping route paths
- Prefix normalization: prefixes are normalized to include a leading slash

## Error Semantics

- Invalid middleware type: an `InvalidArgumentException` is thrown if a resolved middleware does not implement `Psr\Http\Server\MiddlewareInterface`
- Unresolvable module prefix: an `InvalidArgumentException` is thrown if the module name cannot be processed for prefixing

## 🛠️ Development & Testing

Run via Makefile:

```sh
make test         # PHPUnit tests
make codestyle    # PHP CS Fixer
make phpstan      # Static analysis
make devcontainer # Build development container
```

## Ecosystem

- Framework: [power-modules/framework](https://github.com/power-modules/framework)
- Router engine: [League/Route](https://route.thephpleague.com/)
- PSR-15 middleware: [php-fig.org/psr/psr-15](https://www.php-fig.org/psr/psr-15/)

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit: `git commit -m 'feat(router): add amazing feature'`
4. Push: `git push origin feature/amazing-feature`
5. Open a Pull Request

## Support

- Framework repository: [power-modules/framework](https://github.com/power-modules/framework)
- Router repository: [power-modules/router](https://github.com/power-modules/router)