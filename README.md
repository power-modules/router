# Power Modules Router

A sophisticated modular routing component for the Power Modules framework that brings **true module encapsulation** to web routing. Each module manages its own routes and middleware while maintaining complete isolation through dedicated dependency injection containers.

## 🚀 Key Innovations

- **🎯 Module-Centric Routing**: Routes are automatically organized and prefixed by module name—no more manual route group management
- **🔒 True Encapsulation**: Controllers and middleware are resolved from their originating module's DI container, ensuring complete separation of concerns
- **⚡ Zero-Configuration Setup**: Just implement `HasRoutes` and the framework handles the rest—automatic route discovery, prefixing, and registration
- **🛡️ Type-Safe Middleware**: Both route-level and module-level middleware with full PSR-15 compliance and intelligent resolution
- **🔧 Battle-Tested Foundation**: Built on League/Route with enhanced modular capabilities for enterprise-scale applications

## 🎯 Perfect For

- **🏢 Modular Monoliths**: Organize complex applications with clear routing boundaries between modules
- **📦 Plugin Systems**: Each module can define its own routes without conflicts or manual coordination
- **🚀 API Development**: Clean separation of concerns with module-based endpoint organization
- **👥 Team Collaboration**: Different teams can work independently on isolated routing logic
- **🔄 Microservice Preparation**: Routes are already module-isolated, making service extraction effortless

## How It Works

The router extends the Power Modules framework's **module encapsulation principle** to web routing. Each module that implements `HasRoutes` becomes a self-contained routing unit with automatic organization and dependency injection.

- **🔄 Automatic Route Discovery**: The framework scans modules for the `HasRoutes` interface and automatically registers their routes
- **📁 Smart Route Prefixing**: Module names are converted to kebab-case route prefixes (e.g., `UserManagementModule` → `/user-management/`)
- **🎛️ Custom Prefixes**: Override automatic prefixing by implementing `HasCustomRouteSlug` for complete control
- **🔗 Container Resolution**: Controllers and middleware are resolved from their module's DI container, maintaining strict encapsulation

This approach ensures that routing logic stays within module boundaries, making your application truly modular and maintainable.

## Installation

Install via Composer:

```sh
composer require power-modules/router
```

## Requirements

- **PHP**: 8.4+
- **[Power Modules Framework](https://github.com/power-modules/framework)**: ^1.0
- **League/Route**: ^6.2
- **Laminas Diactoros**: ^3.6

## Application Architecture Overview

Here's an example showing how three modules work together: a user module with routes, an auth module that exports middleware, and an API module that imports and uses the auth middleware.

### Module Definitions

#### `UserModule` (Simple Module with Routes)
- Defines user-related routes with automatic `/user/` prefixing

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
            Route::get('/users', UserController::class, 'index'),     // /user/users
            Route::post('/users', UserController::class, 'store'),    // /user/users
            Route::get('/users/{id}', UserController::class, 'show'), // /user/users/{id}
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(UserRepository::class, UserRepository::class);
        $container->set(UserController::class, UserController::class)
            ->addArguments([UserRepository::class]);
    }
}
```

#### `AuthModule` (Module with Exported Middleware)
- Exports authentication middleware for use by other modules

```php
<?php

declare(strict_types=1);

use Modular\Framework\PowerModule\Contract\ExportsComponents;

final readonly class AuthModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [AuthMiddleware::class];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(TokenService::class, TokenService::class);
        $container->set(AuthMiddleware::class, AuthMiddleware::class)
            ->addArguments([TokenService::class]);
    }
}
```

#### `ApiModule` (Module with Imports and Custom Prefix)
- Imports auth middleware and uses custom route prefix

```php
<?php

declare(strict_types=1);

use Modular\Framework\PowerModule\Contract\ImportsComponents;
use Modular\Framework\PowerModule\ImportItem;
use Modular\Router\Contract\HasCustomRouteSlug;

final readonly class ApiModule implements PowerModule, HasRoutes, ImportsComponents, HasCustomRouteSlug
{
    public static function imports(): array
    {
        return [
            ImportItem::create(AuthModule::class, AuthMiddleware::class),
        ];
    }

    public function getRouteSlug(): string
    {
        return '/api/v1';
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/status', StatusController::class)                    // /api/v1/status
                ->addMiddleware(AuthMiddleware::class),
            Route::post('/data', DataController::class, 'store')              // /api/v1/data
                ->addMiddleware(AuthMiddleware::class, ValidationMiddleware::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        // AuthMiddleware is automatically available for injection
        $container->set(StatusController::class, StatusController::class);
        $container->set(ValidationMiddleware::class, ValidationMiddleware::class);
        $container->set(DataController::class, DataController::class);
    }
}
```

### Controller Implementation

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

final readonly class UserController implements RequestHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->index($request);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->userRepository->findAll();
        return new JsonResponse(['users' => $users]);
    }

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        // Create user logic
        return new JsonResponse(['message' => 'User created'], 201);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $user = $this->userRepository->findById($id);
        return new JsonResponse(['user' => $user]);
    }
}
```

## Usage Example

```php
<?php

declare(strict_types=1);

use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\RouterModule;

// Build the modular application
$app = new ModularAppBuilder(__DIR__)->build();

// Add routing setup and register modules
$app->addPowerModuleSetup(new RoutingSetup());
$app->registerModules([
    RouterModule::class,    // Core router module
    AuthModule::class,      // Provides auth middleware
    UserModule::class,      // User routes at /user/*
    ApiModule::class,       // API routes at /api/v1/*
]);

// Get the router and handle requests
$router = $app->get(ModularRouterInterface::class);
$response = $router->handle($request);
```

### The Resulting Route Structure

```
GET  /user/users          → UserController::index()
POST /user/users          → UserController::store()
GET  /user/users/{id}     → UserController::show()
GET  /api/v1/status       → StatusController::handle() [+ AuthMiddleware]
POST /api/v1/data         → DataController::store() [+ AuthMiddleware + ValidationMiddleware]
```

## API Reference

### Route Definition Methods

#### HTTP Methods

```php
Route::get('/path', Controller::class, 'method');
Route::post('/path', Controller::class, 'method');
Route::put('/path', Controller::class, 'method');
Route::patch('/path', Controller::class, 'method');
Route::delete('/path', Controller::class, 'method');
```

#### Route Parameters

```php
Route::get('/users/{id}', UserController::class, 'show');
Route::get('/posts/{id}/comments/{commentId}', CommentController::class, 'show');
```

#### Default Controller Method

If no method is specified, the router defaults to the `handle()` method (RequestHandlerInterface compliance):

```php
Route::get('/users', UserController::class); // Calls handle() method
```

### Module Contracts

- **`HasRoutes`**: Implement to define module routes
- **`HasMiddleware`**: Implement to add module-level middleware  
- **`HasCustomRouteSlug`**: Implement to customize route prefix

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

## Middleware

### Route-Level Middleware

Add middleware to specific routes with automatic resolution from module containers:

```php
Route::get('/protected', ProtectedController::class)
    ->addMiddleware(AuthMiddleware::class, ValidationMiddleware::class);
```

### Module-Level Middleware

Apply middleware to all routes in a module:

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

### Custom Middleware Implementation

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

### Custom Route Prefixes

Override automatic module-name prefixing for complete control:

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

Customize the router's strategy (e.g., use `JsonStrategy`) by creating a configuration file named `modular_router.php` in your application's config directory:

```php
<?php

declare(strict_types=1);

use League\Route\Strategy\JsonStrategy;
use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;

return Config::create()
    ->set(Setting::Strategy, new JsonStrategy(new ResponseFactory()));
```

The router automatically picks up this configuration during application bootstrap.

### Response Decorators

Add global response transformations:

```php
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response->withHeader('X-Custom-Header', 'value');
});
```

## Advanced Usage

### Cross-Module Service Integration

For complex scenarios where modules need to share services across route boundaries:

```php
// SharedServicesModule exports common services
class SharedServicesModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            LoggerInterface::class,
            CacheInterface::class,
            EventDispatcherInterface::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(LoggerInterface::class, FileLogger::class);
        $container->set(CacheInterface::class, RedisCache::class);
        $container->set(EventDispatcherInterface::class, EventDispatcher::class);
    }
}

// Multiple modules can import and use these shared services
class OrderModule implements PowerModule, HasRoutes, ImportsComponents
{
    public static function imports(): array
    {
        return [
            ImportItem::create(SharedServicesModule::class, 
                LoggerInterface::class, 
                EventDispatcherInterface::class
            ),
        ];
    }

    public function getRoutes(): array
    {
        return [
            Route::post('/orders', OrderController::class, 'create'),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        // Shared services are automatically available
        $container->set(OrderController::class, OrderController::class)
            ->addArguments([LoggerInterface::class, EventDispatcherInterface::class]);
    }
}
```

### Error Handling

Implement error handling middleware that works across all modules:

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

- **[Power Modules Framework Documentation](https://github.com/power-modules/framework)**
- **[League/Route Documentation](https://route.thephpleague.com/)**
- **[PSR-15 Middleware Documentation](https://www.php-fig.org/psr/psr-15/)**