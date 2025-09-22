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
            Route::get('/profile', UserController::class, 'show'),
            Route::put('/profile', UserController::class, 'update'),
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

## Route Class

Create HTTP routes with method-specific factories.

```php
// Static factory methods
Route::get(string $path, string $controllerName, string $method = 'handle'): Route
Route::post(string $path, string $controllerName, string $method = 'handle'): Route
Route::put(string $path, string $controllerName, string $method = 'handle'): Route
Route::patch(string $path, string $controllerName, string $method = 'handle'): Route
Route::delete(string $path, string $controllerName, string $method = 'handle'): Route

// Add middleware to specific routes
addMiddleware(string ...$middlewareClassNames): Route
```

**Examples**:
```php
// Basic routes
Route::get('/users', UserController::class);                    // Uses 'handle' method
Route::get('/users/{id}', UserController::class, 'show');       // Custom method
Route::post('/users', UserController::class, 'create');

// Route parameters (handled by League/Route)
Route::get('/users/{id:\d+}', UserController::class, 'show');   // Numeric constraint
Route::get('/posts/{slug:[a-z-]+}', PostController::class);     // Custom regex

// Route middleware
Route::post('/orders', OrderController::class)
    ->addMiddleware(AuthMiddleware::class, ValidationMiddleware::class);
```

## Router Interface

Main router interface for handling requests.

```php
interface ModularRouterInterface extends RequestHandlerInterface
{
    public function registerPowerModuleRoutes(
        PowerModule $powerModule,
        ContainerInterface $moduleContainer,
        ?PowerModuleConfig $powerModuleConfig = null
    ): void;

    public function addResponseDecorator(callable $decorator): self;

    // Inherited from RequestHandlerInterface
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

**Usage**:
```php
// Get router from DI container
$router = $app->get(ModularRouterInterface::class);

// Add global response decorators
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response->withHeader('X-API-Version', '1.0');
});

// Handle requests (typically done by HTTP server)
$response = $router->handle($serverRequest);
```

## Response Decorators

Global response transformations applied to all responses.

**Common Examples**:
```php
// API versioning
$router->addResponseDecorator(fn($res) => $res->withHeader('X-API-Version', 'v1.2'));

// CORS headers
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
});

// Security headers
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY');
});
```

## Configuration

Configure the router via `config/modular_router.php`.

```php
enum Setting
{
    case Strategy; // League\Route strategy for request/response handling
}
```

**Configuration Examples**:
```php
// config/modular_router.php - JSON API
<?php
use League\Route\Strategy\JsonStrategy;
use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;

return Config::create()
    ->set(Setting::Strategy, new JsonStrategy(new ResponseFactory()));
```

## Module Setup

### RouterModule
Provides core router services.

```php
class RouterModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [ModularRouterInterface::class];
    }
}
```

### RoutingSetup
Automatically discovers and registers routes from modules.

```php
$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(new RoutingSetup()) // ← Enables automatic route discovery
    ->withModules(
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

See [Controller Resolution Strategy](architecture.md#controller-resolution-strategy) for technical details.

## Middleware Resolution

Middleware classes are resolved with this precedence:
1. **Router Container**: Check router's internal container first
2. **Module Container**: Fall back to originating module's container
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

## Route Prefix Rules
- Must start with `/` (e.g., `/api/v1`, not `api/v1`)
- Avoid trailing slashes to prevent double-slash URLs
- Module names auto-convert: `UserModule` → `/user`, `ApiGatewayModule` → `/api-gateway`

## PSR Compliance

- **PSR-7**: HTTP message interfaces
- **PSR-11**: Container interface for DI
- **PSR-15**: Request handlers and middleware

For architectural details and design patterns, see the [Architecture Guide](architecture.md).