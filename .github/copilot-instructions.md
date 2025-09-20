# Power Modules Router - AI Coding Agent Instructions

## Architecture Overview

This is a **modular router component** for the Power Modules framework that provides routing capabilities with strict module encapsulation and dependency injection integration.

### Core Components

- **`Router.php`**: Main router implementation that wraps League/Route with modular power module integration
- **`Route.php`**: Route definition class with middleware support and controller reference for DI resolution
- **`RouterModule.php`**: Power Module implementation that exports the router as a service
- **Contracts**: Interface-driven design defining module behaviors (`HasRoutes`, `HasMiddleware`, `HasCustomRouteSlug`)

## Modular Architecture Patterns

### Power Module Integration
- Modules implement `HasRoutes` to define routes via `getRoutes(): array<Route>`
- Route groups are auto-prefixed by module name (e.g., `FooBarModule` → `/foo-bar/`)
- Override prefixes by implementing `HasCustomRouteSlug::getRouteSlug()`
- Module-level middleware via `HasMiddleware::getMiddleware()`

### Dependency Injection Philosophy
Routes specify `controllerName` (class string) instead of instances. The Router registers controllers with module-specific containers using `InstanceViaContainerResolver`, ensuring:
- Controllers are resolved from their originating module's DI container
- Router remains decoupled from controller dependencies
- Promotes modularity and separation of concerns

**Important Constraint**: If multiple modules use the same controller class, the router's internal container will overwrite previous registrations. The controller will always resolve from the last registered module's container, not the original module's container. This is because controller class names are used as registration keys. To avoid this, ensure each module uses unique controller classes.

### Route Definition Patterns
```php
// In module's getRoutes() method
return [
    Route::get('/users', UserController::class, 'index'),
    Route::post('/users', UserController::class, 'store')
        ->addMiddleware(ValidationMiddleware::class),
];
```

## Development Workflow

### Build Commands
- `make test`: Run PHPUnit tests (no coverage)
- `make codestyle`: Check PHP CS Fixer compliance  
- `make phpstan`: Static analysis with PHPStan
- `make devcontainer`: Build Docker dev container

### Testing Patterns
- Unit tests in `test/Unit/` follow `#[CoversClass(ClassName::class)]` attribute pattern
- Test modules in `test/Unit/Sample/` demonstrate middleware and routing integration
- Use `ConfigurableContainer` for DI testing with module registration

### Code Standards
- Strict types enabled (`declare(strict_types=1);`)
- PSR-4 autoloading: `Modular\Router\` → `src/`
- Enum-based HTTP methods (`RouteMethod::Get`, etc.)
- Interface contracts over concrete dependencies

## Configuration System

- **`Config\Config.php`**: Extends `PowerModuleConfig` with default `ApplicationStrategy`
- **`Config\Setting.php`**: Enum-based configuration keys (`Setting::Strategy`)
- Configuration filename: `modular_router`

## Integration Points

### Dependencies
- **League/Route**: Core routing engine (wrapped, not extended)
- **Power Modules Framework**: Module system and DI container
- **Laminas Diactoros**: PSR-7 HTTP message implementation

### Extension Points
- Implement `HasRoutes` for route registration
- Implement `HasMiddleware` for middleware stacks
- Implement `HasCustomRouteSlug` for custom route prefixes
- Use `addResponseDecorator()` for response transformation

## Key Conventions

- Controllers default to `handle()` method if not specified
- Middleware classes must implement PSR-15 `MiddlewareInterface`
- Module names auto-convert to kebab-case route prefixes
- Route middleware resolved from module containers first, then router container