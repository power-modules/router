# Power Modules Router

A modular router component for the Power Modules framework that provides routing capabilities with strict module encapsulation and dependency injection integration.

## Features

- **Modular Architecture**: Routes are organized by Power Modules with automatic prefixing
- **Dependency Injection Integration**: Controllers are resolved from module-specific DI containers
- **Middleware Support**: Both module-level and route-level middleware with PSR-15 compliance
- **Flexible Configuration**: Customizable routing strategies and response decorators
- **League/Route Integration**: Built on top of the proven League/Route package

## Installation

```bash
composer require power-modules/router
```

## Requirements

- PHP 8.4+
- [Power Modules Framework](https://github.com/power-modules/framework)
- League/Route ^6.2
- Laminas Diactoros ^3.6

## Quick Start

### 1. Create a Module with Routes

```php
<?php

declare(strict_types=1);

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

final readonly class UserModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/users', UserController::class, 'handle'),
            Route::post('/users', UserController::class, 'store'),
            Route::get('/users/{id}', UserController::class, 'show'),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(UserController::class, UserController::class);
    }
}
```

### 2. Create a Controller

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

final readonly class UserController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->index($request);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['users' => []]);
    }

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['message' => 'User created']);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        return new JsonResponse(['user' => ['id' => $id]]);
    }
}
```

### 3. Setup the Application

```php
<?php

declare(strict_types=1);

use Modular\Framework\App\Config\Config;
use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\RouterModule;

$app = new ModularAppBuilder(__DIR__)->build();

$app->addPowerModuleSetup(new RoutingSetup());
$app->registerModules([
    RouterModule::class,
    UserModule::class,
]);

$router = $app->get(ModularRouterInterface::class);

// Handle request
$response = $router->handle($request);
```

## Route Definition

### HTTP Methods

```php
Route::get('/path', Controller::class, 'method');
Route::post('/path', Controller::class, 'method');
Route::put('/path', Controller::class, 'method');
Route::patch('/path', Controller::class, 'method');
Route::delete('/path', Controller::class, 'method');
```

### Route Parameters

```php
Route::get('/users/{id}', UserController::class, 'show');
Route::get('/posts/{id}/comments/{commentId}', CommentController::class, 'show');
```

### Default Controller Method

If no method is specified, the router defaults to the `handle()` method (RequestHandlerInterface compliance):

```php
Route::get('/users', UserController::class); // Calls handle() method
```

## Middleware

### Route-Level Middleware

```php
Route::get('/protected', ProtectedController::class)
    ->addMiddleware(AuthMiddleware::class, ValidationMiddleware::class);
```

### Module-Level Middleware

```php
class UserModule implements PowerModule, HasRoutes, HasMiddleware
{
    public function getMiddleware(): array
    {
        return [
            AuthMiddleware::class,
            LoggingMiddleware::class,
        ];
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/users', UserController::class),
        ];
    }
}
```

### Custom Middleware

```php
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request, 
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Logging logic here
        return $handler->handle($request);
    }
}
```

## Route Prefixing

### Automatic Prefixing

Module names are automatically converted to kebab-case route prefixes:

```php
class UserManagementModule implements PowerModule, HasRoutes
{
    // Routes will be prefixed with '/user-management/'
}
```

### Custom Route Prefix

```php
class UserModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/api/v1/users';
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/', UserController::class, 'index'), // /api/v1/users/
            Route::get('/{id}', UserController::class, 'show'), // /api/v1/users/{id}
        ];
    }
}
```
## Configuration

### Custom Strategy

To customize the router's strategy (e.g., use `JsonStrategy`), create a configuration file named `modular_router.php` [`Modular\Router\Config\Config`](src/Config/Config.php) in your application's config directory (`<app_root>/config/`):

```php
<?php

declare(strict_types=1);

use League\Route\Strategy\JsonStrategy;
use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;

return Config::create()
    ->set(Setting::Strategy, new JsonStrategy(new ResponseFactory()))
;
```

The router will automatically pick up this configuration when the application boots.

### Response Decorators

```php
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response->withHeader('X-Custom-Header', 'value');
});
```

## Advanced Usage

### Dependency Injection

Controllers and middlewares are automatically resolved from their module's DI container:

```php
class UserController implements RequestHandlerInterface
{
    public function __construct(
        private UserService $userService,
        private LoggerInterface $logger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->userService->getAllUsers();
        $this->logger->info('Fetched users', ['count' => count($users)]);

        return new JsonResponse(['users' => $users]);
    }
}

// In your module
public function register(ConfigurableContainerInterface $container): void
{
    $container->set(UserService::class, UserService::class);
    $container->set(LoggerInterface::class, FileLogger::class);
    $container->set(
        UserController::class,
        UserController::class
    )->addArguments([
        UserService::class,
        LoggerInterface::class,
    ]);
}
```

### Error Handling

```php
class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request, 
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
```

## API Reference

### Router Interface

```php
interface ModularRouterInterface extends RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer,
        ?PowerModuleConfig $powerModuleConfig,
    ): void;

    public function addResponseDecorator(callable $decorator): ModularRouterInterface;
}
```

### Module Contracts

- **`HasRoutes`**: Implement to define module routes
- **`HasMiddleware`**: Implement to add module-level middleware
- **`HasCustomRouteSlug`**: Implement to customize route prefix

## Development & Testing

Run tests, code style checks, and static analysis using the Makefile:

```sh
make test         # Run PHPUnit tests
make codestyle    # Check code style with PHP CS Fixer
make phpstan      # Run static analysis
make devcontainer # Build development container
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat(...): added amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- [Power Modules Framework Documentation](https://github.com/power-modules/framework)
- [League/Route Documentation](https://route.thephpleague.com/)
- [PSR-15 Middleware Documentation](https://www.php-fig.org/psr/psr-15/)