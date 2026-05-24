# API Reference

Essential API documentation for the Modular Router. For architectural concepts and patterns, see the [Architecture Guide](architecture.md).



## Core Interfaces

### HasRoutes
Interface for modules that define HTTP routes.

```php
interface HasRoutes
{
    /**
     * @return array<Route>
     */
    public function getRoutes(): array;
}
```

**Example**:
```php
class UserModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/profile', ShowUserProfileHandler::class),
            Route::put('/profile', UpdateUserProfileHandler::class),
        ];
    }
}
// Results in: /user/profile
```

### HasCustomRouteSlug
Override automatic route prefixing.

```php
interface HasCustomRouteSlug
{
    public function getRouteSlug(): string;
}
```

**Example**:
```php
class ApiV1Module implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/api/v1'; // ✓ Leading slash, no trailing slash
    }
}
// Results in: /api/v1/users instead of /api-v1/users
```

### HasMiddleware
Define middleware for all module routes.

```php
interface HasMiddleware
{
    /**
     * @return array<class-string<MiddlewareInterface>>
     */
    public function getMiddleware(): array;
}
```

**Example**:
```php
class AdminModule implements PowerModule, HasRoutes, HasMiddleware
{
    public function getMiddleware(): array
    {
        return [AuthMiddleware::class, AdminMiddleware::class];
    }
}
// Middleware runs before all /admin/* routes
```

### HasResponseDecorators
Interface for modules that provide response decorators. See [Advanced Patterns](advanced-patterns.md#response-decorators) for detailed usage.

```php
interface HasResponseDecorators
{
    /**
     * @return array<callable(ResponseInterface):ResponseInterface>
     */
    public function getResponseDecorators(): array;
}
```

**Example**:
```php
class UserModule implements PowerModule, HasRoutes, HasResponseDecorators
{
    public function getResponseDecorators(): array
    {
        // This decorator is applied to all routes in this module
        return [
            fn(ResponseInterface $r): ResponseInterface => $r->withHeader('X-User-Module', 'true')
        ];
    }
}
```

## Route Class

Create HTTP routes with method-specific factories.

```php
// Static factory methods
Route::get(string $path, string $controllerName): Route
Route::post(string $path, string $controllerName): Route
Route::put(string $path, string $controllerName): Route
Route::patch(string $path, string $controllerName): Route
Route::delete(string $path, string $controllerName): Route

// Add middleware to specific routes
addMiddleware(string ...$middlewareClassNames): Route
```

**Examples**:
```php
// Basic routes
Route::get('/users', ListUsersHandler::class);
Route::get('/users/{id}', ShowUserHandler::class);
Route::post('/users', CreateUserHandler::class);

// Native placeholder grammar currently supports basic placeholders only
Route::get('/users/{id}', ShowUserHandler::class);
Route::get('/posts/{slug}', ShowPostHandler::class);

// Route middleware
Route::post('/orders', CreateOrderHandler::class)
    ->addMiddleware(AuthMiddleware::class, ValidationMiddleware::class);

// Route-level response decorators
Route::get('/profile', ShowUserProfileHandler::class)
    ->addResponseDecorator(fn($r) => $r->withHeader('X-Cache-Control', 'no-cache'));
```

Handlers must implement `Psr\Http\Server\RequestHandlerInterface`.

## HTTP Entrypoint Interface

Default composed HTTP entrypoint for applications.

```php
interface HttpEntrypointInterface extends RequestHandlerInterface
{
}
```

**Usage**:
```php
$httpEntrypoint = $app->get(HttpEntrypointInterface::class);
$response = $httpEntrypoint->handle($serverRequest);
```

## Bare Router Interface

Bare router service used for route registration, response decoration, and custom composition.

```php
interface ModularRouterInterface extends RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer
    ): void;

    public function addResponseDecorator(callable $decorator): self;

    // Inherited from RequestHandlerInterface
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

**Usage**:
```php
// Get the bare router from DI when you need advanced composition hooks
$router = $app->get(ModularRouterInterface::class);

// Add global response decorators programmatically
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response->withHeader('X-API-Version', '1.0');
});

// Handle requests directly only when you intentionally want the bare router
$response = $router->handle($serverRequest);
```

## Response Decorators

The router supports response decorators at three levels: global, module-level, and route-level. For detailed examples and execution order, see the [Response Decorators](advanced-patterns.md#response-decorators) section in the advanced patterns guide.

## Default Setup

Register `RoutingModule` and `RouterModule`, then use `RoutingSetup::withDefaults()` to compose the default RFC 7807 synthetic responses, global response decoration, entrypoint exception middleware, and route registration behavior.

```php
use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\RoutingModule;
use Modular\Router\RouterModule;

$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(...RoutingSetup::withDefaults())
    ->withModules(
        RoutingModule::class,
        RouterModule::class,
        UserModule::class,
        AdminModule::class,
    )
    ->build();
```

## Manual Composition

For advanced customization, compose the setup list manually and target another exporting module that provides `SyntheticResponseFactoryInterface`, `ResponseDecoratorChainInterface`, and `HttpEntrypointMiddlewareInterface`.

```php
use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\PowerModule\Setup\HttpEntrypointMiddlewareSetup;
use Modular\Router\PowerModule\Setup\ResponseDecoratorChainSetup;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\PowerModule\Setup\SyntheticResponseSetup;
use Modular\Router\RouterModule;

$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(
        new HttpEntrypointMiddlewareSetup(ApiHttpModule::class),
        new ResponseDecoratorChainSetup(ApiHttpModule::class),
        new SyntheticResponseSetup(ApiHttpModule::class),
        new RoutingSetup(),
    )
    ->withModules(
        ApiHttpModule::class,
        RouterModule::class,
        UserModule::class,
    )
    ->build();
```

## Module Setup

### RouterModule
Provides core router services.

```php
class RouterModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [ModularRouterInterface::class, HttpEntrypointInterface::class];
    }
}
```

### RoutingSetup
Automatically discovers and registers routes from modules.

```php
$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(...RoutingSetup::withDefaults()) // ← Enables automatic route discovery with the default routing composition
    ->withModules(
        RoutingModule::class,
        RouterModule::class,
        UserModule::class,   // Routes: /user/*
        AdminModule::class,  // Routes: /admin/*
    )
    ->build();
```

## Controller Resolution

Controllers are resolved from their originating module's container, maintaining proper encapsulation.

- Uses fully qualified class names (e.g., `App\User\UserController`)
- No naming conflicts between modules with different namespaces

See [Controller Resolution Flow](architecture.md#controller-resolution-flow) for technical details.

## Middleware Resolution

Middleware classes are resolved with this precedence:
1. **Router Container**: Exported/shared middleware (via `ExportsComponents`)
2. **Module Container**: Module-private middleware (not exported)
3. **Error**: Throw `InvalidArgumentException` if not found

**Requirements**:
- Must implement `Psr\Http\Server\MiddlewareInterface`
- Must be registered in router or module container

## Error Handling

### Common Exceptions

**InvalidArgumentException** - Thrown when:
- Middleware doesn't implement `MiddlewareInterface`
- Middleware class not found in any container
- Invalid controller class name

### HTTP Errors
- **404 Not Found**: No matching route
- **405 Method Not Allowed**: Route exists but wrong HTTP method
- **Default payload contract**: Router-owned 404 and 405 responses, and the default unhandled 500 response, emit RFC 7807 problem-details JSON
- **500+ Exception Responses**: Produced by the composed `HttpEntrypointInterface` via the configured `HttpEntrypointMiddlewareInterface`

## Route Prefix Rules
- Must start with `/` (e.g., `/api/v1`, not `api/v1`)
- Avoid trailing slashes to prevent double-slash URLs
- Module names auto-convert: `UserModule` → `/user`, `ApiGatewayModule` → `/api-gateway`

## PSR Compliance

- **PSR-7**: HTTP message interfaces
- **PSR-11**: Container interface for DI
- **PSR-15**: Request handlers and middleware

For architectural details and design patterns, see the [Architecture Guide](architecture.md).