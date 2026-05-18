# Modular Router

[![CI](https://github.com/power-modules/router/actions/workflows/php.yml/badge.svg)](https://github.com/power-modules/router/actions/workflows/php.yml)
[![Packagist Version](https://img.shields.io/packagist/v/power-modules/router)](https://packagist.org/packages/power-modules/router)
[![PHP Version](https://img.shields.io/packagist/php-v/power-modules/router)](https://packagist.org/packages/power-modules/router)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-blue)](#)

A **modular router component** for the Power Modules framework that provides HTTP routing capabilities with strict module encapsulation and dependency injection integration.

> **🔌 Perfect Integration**: Built specifically for the Power Modules ecosystem with automatic route discovery, module-scoped controllers, and composable middleware stacks.

## ✨ Why Modular Router?

- **🏗️ Module-Centric**: Routes are owned and defined by individual modules
- **🔒 Encapsulated Controllers**: Controllers resolve from their originating module's DI container
- **🎯 Auto-Prefixing**: Module names automatically become URL prefixes (`UserModule` → `/user/*`)
- **🧩 Composable Middleware**: Module-level and route-level middleware stacking
- **⚡ Zero Configuration**: Automatic route discovery with convention-based patterns
- **🛡️ Type-Safe**: Full PHP 8.4+ type system integration with enums and strict typing

## Quick Start

```bash
composer require power-modules/router
```

```php
<?php

use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\RouterModule;
use Modular\Router\PowerModule\Setup\RoutingSetup;

$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(new RoutingSetup())
    ->withModules(
        RouterModule::class,           // Provides routing infrastructure
        \MyApp\User\UserModule::class, // Your modules with routes
        \MyApp\Admin\AdminModule::class,
    )
    ->build();

// Get router and handle PSR-7 requests
$router = $app->get(\Modular\Router\Contract\ModularRouterInterface::class);
$response = $router->handle($serverRequest);
```

## 📚 Documentation

| Guide | Description |
|-------|-------------|
| **[Getting Started](docs/getting-started.md)** | Build your first routed module in 5 minutes |
| **[Architecture](docs/architecture.md)** | Module boundaries, container hierarchy, and request lifecycle |
| **[Use Cases](docs/use-cases/README.md)** | Web APIs, admin panels, plugin systems, and microservices |
| **[API Reference](docs/api-reference.md)** | Complete interface and class documentation |
| **[Advanced Patterns](docs/advanced-patterns.md)** | Custom strategies, response decorators, and optimization |

## Real-World Examples

### Simple Module with Routes
```php
<?php

use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Route;

class UserModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/profile', ShowUserProfileHandler::class),
            Route::put('/profile', UpdateUserProfileHandler::class),
            Route::post('/avatar', UploadUserAvatarHandler::class)
                ->addMiddleware(AuthMiddleware::class),
        ];
    }
    
    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(UserController::class, UserController::class)
            ->addArguments([UserService::class, LoggerInterface::class]);
    }
}
// Results in: /user/profile, /user/avatar
```

### Module with Custom Prefix
```php
<?php

use Modular\Router\Contract\HasCustomRouteSlug;

class ApiV1Module implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/api/v1';
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/users', ListApiUsersHandler::class),
            Route::post('/users', CreateApiUserHandler::class),
            Route::get('/health', HealthCheckHandler::class),
        ];
    }
}
// Results in: /api/v1/users, /api/v1/health
```

### Module with Middleware Stack
```php
<?php

use Modular\Router\Contract\HasMiddleware;

class AdminModule implements PowerModule, HasRoutes, HasMiddleware
{
    public function getMiddleware(): array
    {
        return [
            AuthMiddleware::class,     // All admin routes require auth
            AdminMiddleware::class,    // All admin routes require admin role
            AuditMiddleware::class,    // Log all admin actions
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/dashboard', AdminDashboardHandler::class),
            Route::delete('/users/{id}', DeleteAdminUserHandler::class)
                ->addMiddleware(ConfirmationMiddleware::class), // Extra confirmation
        ];
    }
}
// Middleware order: Auth → Admin → Audit → [Confirmation] → Controller
```

## 🏗️ Architecture Highlights

### Module Encapsulation
- **Route Ownership**: Each module defines its own routes via `HasRoutes`
- **Controller Resolution**: Controllers are resolved from their originating module's container
- **Dependency Isolation**: Module dependencies stay within module boundaries

### Automatic Discovery
- **Convention-Based**: `UserModule` automatically gets `/user/*` prefix
- **Zero Config**: Routes are discovered and registered automatically during app build
- **Override Ready**: Implement `HasCustomRouteSlug` for custom prefixes

### Middleware Composition
- **Module-Level**: Applied to all routes in the module
- **Route-Level**: Applied to specific routes only
- **Resolution Priority**: Module container first, then router container

### Response Transformation
- **Global Decorators**: Applied to all responses
- **Module-Level Decorators**: Via `HasResponseDecorators` interface
- **Route-Level Decorators**: Fluent API on `Route` definitions

## 🚀 Features

### Route Definition
```php
// HTTP method factories with intuitive API
Route::get('/users', ListUsersHandler::class);
Route::post('/users', CreateUserHandler::class);
Route::put('/users/{id}', UpdateUserHandler::class);
Route::delete('/users/{id}', DeleteUserHandler::class);

// Each route points to a RequestHandlerInterface implementation
Route::get('/health', HealthCheckHandler::class);

// Fluent middleware chaining
Route::post('/orders', CreateOrderHandler::class)
    ->addMiddleware(AuthMiddleware::class, ValidationMiddleware::class);
```

### Response Decorators
The router supports response decorators at three levels: global, module, and route.

```php
// 1. Global Decorator (applied to all routes)
$router->addResponseDecorator(fn(ResponseInterface $r) => $r->withHeader('X-Global', 'true'));

// 2. Module-Level Decorator (applied to all routes in a module)
class UserModule implements PowerModule, HasRoutes, HasResponseDecorators
{
    public function getResponseDecorators(): array
    {
        return [fn(ResponseInterface $r) => $r->withHeader('X-Module', 'true')];
    }
    // ...
}

// 3. Route-Level Decorator (applied to a single route)
Route::get('/profile', UserController::class)
    ->addResponseDecorator(fn(ResponseInterface $r) => $r->withHeader('X-Route', 'true'));
```

### Configuration System
```php
// config/modular_router.php
<?php

use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;
use Modular\Router\Strategy\JsonRouterStrategy;

return Config::create()
    ->set(Setting::Strategy, new JsonRouterStrategy(new ResponseFactory()));
```

## 🛠️ Development

### Build Commands
```bash
make test        # Run PHPUnit tests
make codestyle   # Check PHP CS Fixer compliance
make phpstan     # Run PHPStan static analysis (level 8)
make devcontainer # Build Docker development container
```

### Code Standards
- **Strict Types**: `declare(strict_types=1);` on every file
- **PSR-12**: Code style with additional rules (trailing commas, ordered imports)
- **PHPStan Level 8**: Maximum static analysis coverage
- **PHP 8.4+**: Latest language features and type system

## 🔌 Integration

### Framework Dependencies
- **Power Modules Framework**: Core module system and DI container
- **Laminas Diactoros**: Default PSR-7 response implementation for native strategies
- **PSR-7/PSR-15**: HTTP message and middleware interfaces

### Extension Points
- **Custom Strategies**: Replace default request/response handling
- **Response Decorators**: Global, module-level, and route-level response transformations
- **Middleware Stacks**: Composable request/response processing
- **Route Prefixes**: Custom URL organization patterns

## 📊 Use Cases

- **🌐 Web APIs**: RESTful APIs with module-organized endpoints
- **🏢 Admin Panels**: Admin interfaces with role-based middleware
- **🔌 Plugin Systems**: Third-party module integration
- **🚀 Microservices**: Service-oriented architectures
- **📱 Mobile Backends**: API backends for mobile applications

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup and guidelines.

## License

MIT License. See [LICENSE](LICENSE) for details.