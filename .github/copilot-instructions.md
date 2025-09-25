# Power Modules Router - AI Coding Agent Instructions

## Architecture Overview

This is a **modular router component** for the Power Modules framework that provides routing capabilities with strict module encapsulation and dependency injection integration.

### Core Components

- **`Router.php`**: Main router implementation that wraps League/Route with modular power module integration
- **`Route.php`**: Route definition class with middleware support and controller reference for DI resolution
- **`RouterModule.php`**: Power Module implementation that exports the router as a service
- **`RouteGroupPrefixResolver.php`**: Handles automatic kebab-case conversion of module names to URL prefixes
- **Contracts**: Interface-driven design defining module behaviors (`HasRoutes`, `HasMiddleware`, `HasCustomRouteSlug`, `HasResponseDecorators`)

## Modular Architecture Patterns

### Power Module Integration
- Modules implement `HasRoutes` to define routes via `getRoutes(): array<Route>`
- Route groups are auto-prefixed by module name (e.g., `LibraryAModule` → `/library-a/`)
- Prefix logic: strips "Module" suffix, converts PascalCase to kebab-case, adds leading slash
- Override prefixes by implementing `HasCustomRouteSlug::getRouteSlug()`
- Module-level middleware via `HasMiddleware::getMiddleware()`
- Module-level response decorators via `HasResponseDecorators::getResponseDecorators()`

### Dependency Injection Philosophy
Routes specify `controllerName` (class string) instead of instances. The Router registers controllers with module-specific containers using `InstanceViaContainerResolver`, ensuring:
- Controllers are resolved from their originating module's DI container using fully qualified class names
- Router remains decoupled from controller dependencies
- Promotes modularity and separation of concerns
- No class name conflicts due to namespace separation (e.g., `App\User\UserController` vs `App\Admin\UserController`)

### Controller Resolution Process
1. **Registration**: `$container->set($fullyQualifiedClassName, $moduleContainer, InstanceViaContainerResolver::class)`
2. **Resolution**: `InstanceViaContainerResolver` calls `$moduleContainer->get($fullyQualifiedClassName)`
3. **Result**: Controller instantiated from correct module with proper dependencies

### Route Definition Patterns
```php
// In module's getRoutes() method
return [
    Route::get('/users', UserController::class, 'index'),
    Route::post('/users', UserController::class, 'store')
        ->addMiddleware(ValidationMiddleware::class),
    Route::get('/profile', ProfileController::class)
        ->addResponseDecorator(fn($r) => $r->withHeader('X-Custom', 'true')),
    // Method defaults to 'handle' if not specified
    Route::get('/profile', ProfileController::class),
];
```

### Middleware Resolution Chain
- **Route-level middleware**: Resolved from module container first, then router container
- **Module-level middleware**: Applied to all routes in the module
- **Precedence**: Module middleware → Route middleware → Controller
- All middleware must implement PSR-15 `MiddlewareInterface`

## Development Workflow

### Build Commands
- `make test`: Run PHPUnit tests (no coverage)
- `make codestyle`: Check PHP CS Fixer compliance
- `make phpstan`: Static analysis with PHPStan (level 8)
- `make devcontainer`: Build Docker dev container

### Testing Patterns
- Unit tests in `test/Unit/` follow `#[CoversClass(ClassName::class)]` attribute pattern
- Test modules in `test/Unit/Sample/` demonstrate middleware and routing integration
- Use `ConfigurableContainer` for DI testing with module registration
- Test both route resolution and middleware execution in RouterTest.php

### Code Standards
- **Strict types**: `declare(strict_types=1);` on every file
- **PSR-4 autoloading**: `Modular\Router\` → `src/`
- **Enum-based HTTP methods**: `RouteMethod::Get`, `RouteMethod::Post`, etc.
- **Interface contracts**: Prefer interfaces over concrete dependencies
- **PHP CS Fixer**: PSR-12 + custom rules (trailing commas, ordered imports, no unused imports)
- **PHPStan**: Level 8 analysis for maximum type safety

## Configuration System

- **`Config\Config.php`**: Extends `PowerModuleConfig` with default `ApplicationStrategy`
- **`Config\Setting.php`**: Enum-based configuration keys (`Setting::Strategy`)
- Configuration filename: `modular_router`

## Integration Points

### Dependencies
- **League/Route**: Core routing engine (wrapped, not extended)
- **Power Modules Framework**: Module system and DI container
- **Laminas Diactoros**: PSR-7 HTTP message implementation (dev/test dependency)

### Extension Points
- Implement `HasRoutes` for route registration
- Implement `HasMiddleware` for middleware stacks  
- Implement `HasCustomRouteSlug` for custom route prefixes
- Implement `HasResponseDecorators` for module-level response decorators
- Use `addResponseDecorator()` on `Route` for route-level decorators
- Use `addResponseDecorator()` on `Router` for global response decorators
- Replace default `ApplicationStrategy` via config for custom response handling, including adding global decorators to the strategy instance.

## Key Conventions

- **Controllers**: Default to `handle()` method if not specified in route definition
- **Controller Resolution**: Uses fully qualified class names via `InstanceViaContainerResolver` from module containers
- **Middleware**: Must implement PSR-15 `MiddlewareInterface`
- **Module naming**: Auto-converts to kebab-case route prefixes (LibraryAModule → /library-a/)
- **Route middleware**: Resolved from module containers first, then router container
- **PHP version**: Requires PHP 8.4+ for latest enum and type system features
- **No Class Conflicts**: Namespace separation prevents controller name collisions naturally