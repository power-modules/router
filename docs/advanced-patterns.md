# Advanced Patterns

Advanced routing patterns and optimization techniques for the Modular Router.

## Response Decorators

Response decorators allow you to transform `ResponseInterface` objects at different levels of the application, providing a powerful way to manage headers, cookies, and other response attributes consistently.

### Global Decorators

Global decorators are applied to every response handled by the router. They are ideal for cross-cutting concerns like adding security headers, API versioning, or performance metrics.

There are two ways to add global decorators: programmatically via the router interface or by exporting a preconfigured response decorator chain from a dedicated module.

#### Programmatic Registration

You can add decorators directly to the router instance, which is useful for dynamic decorators or when you have access to the application's service container.

```php
$router = $app->get(ModularRouterInterface::class);

// Add security headers to all responses
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY');
});
```

#### Module-Based Registration

For static, application-wide decorators, export a preconfigured response decorator chain from a dedicated module and point the setup bundle at that module.

```php
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Modular\Router\ExceptionHandlingMiddleware;
use Modular\Router\Response\ResponseDecoratorChain;
use Modular\Router\Response\SyntheticResponseFactory;
use Psr\Http\Message\ResponseInterface;

final class ApiHttpModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            SyntheticResponseFactoryInterface::class,
            ResponseDecoratorChainInterface::class,
            HttpEntrypointMiddlewareInterface::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $responseDecoratorChain = new ResponseDecoratorChain();
        $responseDecoratorChain->addResponseDecorator(fn(ResponseInterface $r): ResponseInterface => $r->withHeader('X-API-Version', '1.0'));
        $responseDecoratorChain->addResponseDecorator(fn(ResponseInterface $r): ResponseInterface => $r->withHeader('X-Powered-By', 'Power-Modules'));

        $container->set(SyntheticResponseFactoryInterface::class, SyntheticResponseFactory::class);
        $container->set(ResponseDecoratorChainInterface::class, $responseDecoratorChain);
        $container->set(HttpEntrypointMiddlewareInterface::class, ExceptionHandlingMiddleware::class)
            ->addArguments([ResponseDecoratorChainInterface::class]);
    }
}
```

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
        UserApiModule::class,
    )
    ->build();
```

> **Note:** Global decorators apply to matched route responses, router-owned synthetic responses, and exception responses. Module-level and route-level decorators still apply only to matched route responses.

### Module-Level Decorators

Modules can provide their own response decorators by implementing the `HasResponseDecorators` interface. These decorators are applied only to routes defined within that module, making them perfect for module-specific headers or transformations.

```php
use Modular\Router\Contract\HasResponseDecorators;
use Psr\Http\Message\ResponseInterface;

class UserApiModule implements PowerModule, HasRoutes, HasResponseDecorators
{
    public function getResponseDecorators(): array
    {
        // This header will only be added to routes in UserApiModule
        return [
            fn(ResponseInterface $r): ResponseInterface => $r->withHeader('X-Module-Scope', 'User-API')
        ];
    }

    public function getRoutes(): array
    {
        return [
            Route::get('/users', UserController::class), // Gets X-Module-Scope header
        ];
    }
    // ...
}
```

### Route-Level Decorators

For maximum granularity, decorators can be applied directly to a specific route using a fluent API. This is useful for adding conditional headers or metadata to a single endpoint.

```php
use Psr\Http\Message\ResponseInterface;

Route::get('/profile', UserController::class)
    ->addResponseDecorator(
        fn(ResponseInterface $r): ResponseInterface => $r->withHeader('X-Cache-Control', 'no-cache')
    );
```

### Decorator Execution Order

Decorators are executed in an "inside-out" order, allowing for predictable response transformations. The order of application is as follows:

1.  **Global Decorators**: Applied first.
2.  **Module Decorators**: Applied second.
3.  **Route Decorators**: Applied last.

This order means that route-specific decorators can act on a response that has already been modified by global and module-level decorators, giving them the final say on the response content and headers.

Synthetic responses such as 404, 405, and synthetic OPTIONS responses receive only global decorators, because there is no matched module or route for module-level or route-level decorators to run against.

## Custom Synthetic Responses

Override the default synthetic response factory by exporting your own implementation from a dedicated module and wiring `SyntheticResponseSetup` to that module.

### Custom Synthetic Response Factory

For an API-first application, you can create a custom synthetic response factory class that extends the default RFC 7807 implementation and customizes router-owned problem details in one place.

First, define your custom synthetic response factory:

```php
// src/Http/Response/MyApiSyntheticResponseFactory.php
namespace MyApp\Http\Response;

use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Response\SyntheticResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MyApiSyntheticResponseFactory extends SyntheticResponseFactory
{
    public function __construct()
    {
        parent::__construct(new ResponseFactory());
    }

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface
    {
        $response = parent::createNotFoundResponse($request);
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        $payload['type'] = 'https://example.com/problems/not-found';

        $rewritten = $response->withBody(new \Laminas\Diactoros\Stream('php://temp', 'wb+'));
        $rewritten->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $rewritten->getBody()->rewind();

        return $rewritten;
    }
}
```

Then, export it from a dedicated module:

```php
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Modular\Router\ExceptionHandlingMiddleware;
use Modular\Router\Response\ResponseDecoratorChain;
use MyApp\Http\Response\MyApiSyntheticResponseFactory;

final class MyApiHttpModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            SyntheticResponseFactoryInterface::class,
            ResponseDecoratorChainInterface::class,
            HttpEntrypointMiddlewareInterface::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(SyntheticResponseFactoryInterface::class, new MyApiSyntheticResponseFactory());
        $container->set(ResponseDecoratorChainInterface::class, ResponseDecoratorChain::class);
        $container->set(HttpEntrypointMiddlewareInterface::class, ExceptionHandlingMiddleware::class)
            ->addArguments([ResponseDecoratorChainInterface::class]);
    }
}
```

Then compose the router setups manually for that module:

```php
$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(
        new HttpEntrypointMiddlewareSetup(MyApiHttpModule::class),
        new ResponseDecoratorChainSetup(MyApiHttpModule::class),
        new SyntheticResponseSetup(MyApiHttpModule::class),
        new RoutingSetup(),
    )
    ->withModules(
        MyApiHttpModule::class,
        RouterModule::class,
        UserApiModule::class,
    )
    ->build();
```

This pattern keeps the custom composition in module wiring and centralizes your global response logic.

### Custom Problem Types

```php
use Modular\Router\Response\SyntheticResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ApiSyntheticResponseFactory extends SyntheticResponseFactory
{
    public function createMethodNotAllowedResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        $response = parent::createMethodNotAllowedResponse($request, $allowedMethods);
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        $payload['type'] = 'https://example.com/problems/method-not-allowed';

        $rewritten = $response->withBody(new \Laminas\Diactoros\Stream('php://temp', 'wb+'));
        $rewritten->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $rewritten->getBody()->rewind();

        return $rewritten;
    }
}
```

## Custom Entrypoint Middleware

Exception handling lives outside the bare router in the composed HTTP entrypoint. Customize exception policy by exporting an `HttpEntrypointMiddlewareInterface` implementation from a dedicated module and wiring `HttpEntrypointMiddlewareSetup` to that module.

```php
use Laminas\Diactoros\ResponseFactory;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Modular\Router\Response\ResponseDecoratorChain;
use Modular\Router\Response\SyntheticResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class ProblemDetailsHttpModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            SyntheticResponseFactoryInterface::class,
            ResponseDecoratorChainInterface::class,
            HttpEntrypointMiddlewareInterface::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(SyntheticResponseFactoryInterface::class, SyntheticResponseFactory::class);
        $container->set(ResponseDecoratorChainInterface::class, ResponseDecoratorChain::class);
        $container->set(HttpEntrypointMiddlewareInterface::class, new class () implements HttpEntrypointMiddlewareInterface {
            private readonly ResponseFactory $responseFactory;

            public function __construct()
            {
                $this->responseFactory = new ResponseFactory();
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                try {
                    return $handler->handle($request);
                } catch (Throwable $throwable) {
                    $response = $this->responseFactory
                        ->createResponse(500, 'Internal Server Error')
                        ->withHeader('Content-Type', 'application/problem+json');

                    $response->getBody()->write((string) json_encode([
                        'type' => 'https://example.com/problems/internal-server-error',
                        'title' => 'Internal Server Error',
                        'status' => 500,
                        'detail' => 'Please contact support if the problem persists.',
                    ], JSON_THROW_ON_ERROR));

                    return $response;
                }
            }
        });
    }
}
```

```php
$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(
        new HttpEntrypointMiddlewareSetup(ProblemDetailsHttpModule::class),
        new ResponseDecoratorChainSetup(ProblemDetailsHttpModule::class),
        new SyntheticResponseSetup(ProblemDetailsHttpModule::class),
        new RoutingSetup(),
    )
    ->withModules(
        ProblemDetailsHttpModule::class,
        RouterModule::class,
        UserApiModule::class,
    )
    ->build();
```

The default router already emits RFC 7807 responses. Override the entrypoint middleware only when you want richer problem types or additional fields.

## Route Organization

### Custom Route Prefixes

Override default module-based prefixes for cleaner URLs:

```php
final readonly class ApiModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/api/v1';  // Instead of default /api-module
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/users', UserController::class),      // /api/v1/users
            Route::get('/orders', OrderController::class),    // /api/v1/orders
        ];
    }
}
```

### Nested Route Groups

Organize related routes with consistent patterns:

```php
final readonly class UserModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            // Public user routes
            Route::get('/users', ListUsersHandler::class),
            Route::get('/users/{id}', ShowUserHandler::class),
            
            // Protected user routes
            Route::post('/users', CreateUserHandler::class)
                ->addMiddleware(AuthMiddleware::class),
            Route::put('/users/{id}', UpdateUserHandler::class)
                ->addMiddleware(AuthMiddleware::class),
                
            // Admin-only routes
            Route::delete('/users/{id}', DeleteUserHandler::class)
                ->addMiddleware(AuthMiddleware::class, AdminMiddleware::class),
        ];
    }
}
```

## Middleware Patterns

### Module-Level Middleware

Apply middleware to all routes in a module:

```php
final readonly class ApiModule implements PowerModule, HasRoutes, HasMiddleware
{
    public function getMiddleware(): array
    {
        return [
            CorsMiddleware::class,      // Handle CORS for all API routes
            RateLimitMiddleware::class, // Rate limiting
            LoggingMiddleware::class,   // Request logging
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/health', HealthController::class),
            Route::get('/users', UserController::class)
                ->addMiddleware(AuthMiddleware::class), // Route-specific middleware
        ];
    }
}
```

### Conditional Middleware

Apply middleware based on route characteristics:

```php
final readonly class AuthModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            // Public routes - no middleware
            Route::get('/login', ShowLoginHandler::class),
            Route::post('/login', LoginHandler::class),
            
            // Protected routes - auth required
            Route::get('/profile', UserProfileHandler::class)
                ->addMiddleware(AuthMiddleware::class),
            
            // Admin routes - auth + admin permissions
            Route::get('/admin/dashboard', AdminDashboardHandler::class)
                ->addMiddleware(AuthMiddleware::class, AdminMiddleware::class),
        ];
    }
}
```

## Performance Optimization

### Route-Specific Caching

Cache responses for specific routes using middleware:

```php
final readonly class CacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 3600
    ) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return $handler->handle($request);
        }
        
        $cacheKey = 'route:' . md5($request->getUri()->getPath());
        
        if ($this->cache->has($cacheKey)) {
            $response = $this->cache->get($cacheKey);
            return $response->withHeader('X-Cache', 'HIT');
        }
        
        $response = $handler->handle($request);
        
        if ($response->getStatusCode() === 200) {
            $this->cache->set($cacheKey, $response, $this->ttl);
        }
        
        return $response->withHeader('X-Cache', 'MISS');
    }
}

// Apply to specific routes
final readonly class ProductModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            // Cache product listings
            Route::get('/products', ListProductsHandler::class)
                ->addMiddleware(new CacheMiddleware($this->cache, 3600)),
            
            // Don't cache mutations
            Route::post('/products', CreateProductHandler::class),
        ];
    }
}
```

### Response Timing

Track route performance with response decorators:

```php
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response->withHeader('X-Response-Time', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
});
```

## Testing Patterns

### Route Testing

Test route behavior in isolation:

```php
class RouteTest extends TestCase
{
    public function testUserRoutes(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withPowerSetup(...RoutingSetup::withDefaults())
            ->withModules(RoutingModule::class, RouterModule::class, UserModule::class)
            ->build();
        
        $httpEntrypoint = $app->get(\Modular\Router\Contract\HttpEntrypointInterface::class);
        
        $request = new ServerRequest('GET', '/user/profile');
        $response = $httpEntrypoint->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    public function testProtectedRoute(): void
    {
        $router = $this->createRouter();
        
        // Test without auth
        $request = new ServerRequest('GET', '/api/protected');
        $response = $router->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test with auth
        $authRequest = $request->withHeader('Authorization', 'Bearer token');
        $authResponse = $router->handle($authRequest);
        $this->assertEquals(200, $authResponse->getStatusCode());
    }
}
```

## Error Handling

### Global Error Responses

Transform error responses consistently:

```php
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    if ($response->getStatusCode() >= 400) {
        $body = json_decode($response->getBody()->getContents(), true);
        $body['error_id'] = uniqid();
        $body['timestamp'] = date('c');
        
        return new JsonResponse($body, $response->getStatusCode());
    }
    
    return $response;
});
```

### Exception Middleware

Handle exceptions at the route level:

```php
final readonly class ApiExceptionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return new JsonResponse(['error' => 'Validation failed', 'details' => $e->getErrors()], 422);
        } catch (AuthenticationException $e) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Internal server error'], 500);
        }
    }
}
```

## Best Practices

1. **Use response decorators** for cross-cutting concerns like headers and logging
2. **Layer middleware strategically** - module-level for common concerns, route-level for specific needs
3. **Custom route slugs** for clean, meaningful URLs
4. **Cache selectively** using middleware on read-heavy routes
5. **Test route behavior** independently of business logic
6. **Handle errors consistently** with global decorators and exception middleware