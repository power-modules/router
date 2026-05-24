# Power Modules Router - AI Coding Agent Instructions

## Architecture Overview

This is a **modular router component** for the Power Modules framework that provides routing capabilities with strict module encapsulation and dependency injection integration.

### Core Components

- **`Router.php`**: Main router implementation with native route registration, compilation, matching, and dispatch
- **`Route.php`**: Route definition class with middleware support and handler class references for DI resolution
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
Routes specify a handler class string instead of instances. The router stores the originating module container for each route and resolves handlers lazily at dispatch time, ensuring:
- Handlers are resolved from their originating module's DI container using fully qualified class names
- Router remains decoupled from handler dependencies
- Promotes modularity and separation of concerns
- No class name conflicts due to namespace separation (e.g., `App\User\ShowProfileHandler` vs `App\Admin\ShowProfileHandler`)

### Handler Resolution Process
1. **Registration**: Router stores the fully qualified handler class name with its originating module container
2. **Resolution**: The middleware pipeline calls `$moduleContainer->get($fullyQualifiedClassName)` during dispatch
3. **Result**: Handler instantiated from the correct module with proper dependencies

### Route Definition Patterns
```php
// In module's getRoutes() method
return [
    Route::get('/users', ListUsersHandler::class),
    Route::post('/users', CreateUserHandler::class)
        ->addMiddleware(ValidationMiddleware::class),
    Route::get('/profile', ShowProfileHandler::class)
        ->addResponseDecorator(fn($r) => $r->withHeader('X-Custom', 'true')),
    Route::get('/health', HealthCheckHandler::class),
];
```

### Middleware Resolution Chain
- **Route-level middleware**: Resolved from module container first, then router container
- **Module-level middleware**: Applied to all routes in the module
- **Precedence**: Module middleware → Route middleware → RequestHandlerInterface
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

## Setup System

- **`RoutingModule.php`**: Exports the default RFC 7807 `SyntheticResponseFactoryInterface`, `ResponseDecoratorChainInterface`, and `HttpEntrypointMiddlewareInterface`
- **`RouterModule.php`**: Exports the bare router and composed HTTP entrypoint services
- **`PowerModule\Setup\RoutingSetup::withDefaults()`**: Returns the recommended default setup bundle
- **`PowerModule\Setup\SyntheticResponseSetup.php`**: Advanced manual composition seam for targeting another exporting module
- **`PowerModule\Setup\ResponseDecoratorChainSetup.php`**: Advanced manual composition seam for targeting another exporting module
- **`PowerModule\Setup\HttpEntrypointMiddlewareSetup.php`**: Advanced manual composition seam for targeting another exporting module

## Integration Points

### Dependencies
- **Power Modules Framework**: Module system and DI container
- **Laminas Diactoros**: PSR-7 HTTP message implementation

### Extension Points
- Implement `HasRoutes` for route registration
- Implement `HasMiddleware` for middleware stacks  
- Implement `HasCustomRouteSlug` for custom route prefixes
- Implement `HasResponseDecorators` for module-level response decorators
- Use `addResponseDecorator()` on `Route` for route-level decorators
- Use `addResponseDecorator()` on `Router` for global response decorators
- Default router-owned 404 and 405 responses, and the default generic 500 response, use RFC 7807 problem-details JSON.
- Use `RoutingSetup::withDefaults()` together with `RoutingModule` and `RouterModule` for the default setup path.
- Compose `SyntheticResponseSetup`, `ResponseDecoratorChainSetup`, `HttpEntrypointMiddlewareSetup`, and `RoutingSetup` manually when another module should export custom synthetic responses, global decorators, or entrypoint middleware.

## Key Conventions

- **Handlers**: Route targets must implement PSR-15 `RequestHandlerInterface`
- **Handler Resolution**: Uses fully qualified class names resolved from originating module containers
- **Middleware**: Must implement PSR-15 `MiddlewareInterface`
- **Module naming**: Auto-converts to kebab-case route prefixes (LibraryAModule → /library-a/)
- **Route middleware**: Resolved from module containers first, then router container
- **PHP version**: Requires PHP 8.4+ for latest enum and type system features
- **No Class Conflicts**: Namespace separation prevents handler name collisions naturally