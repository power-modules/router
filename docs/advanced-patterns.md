# Advanced Patterns

Advanced routing patterns and optimization techniques for the Modular Router.

## Response Decorators

Transform responses globally without modifying individual controllers:

```php
$router = $app->get(ModularRouterInterface::class);

// Add API versioning and metadata
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response
        ->withHeader('X-API-Version', 'v1.2.0')
        ->withHeader('X-Powered-By', 'Power-Modules-Router');
});

// Add security headers
$router->addResponseDecorator(function (ResponseInterface $response): ResponseInterface {
    return $response
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY');
});
```

## Custom Router Strategies

Override the default League/Route strategy for specialized routing behavior:

```php
// config/modular_router.php
use Laminas\Diactoros\ResponseFactory;
use League\Route\Strategy\JsonStrategy;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;

return Config::create()
    ->set(Setting::Strategy, new JsonStrategy(new ResponseFactory()));
```

### Custom Strategy for API Responses

```php
use League\Route\Strategy\ApplicationStrategy;
use Psr\Http\Message\ResponseInterface;

class ApiStrategy extends ApplicationStrategy
{
    protected function decorateResponse(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-API-Framework', 'Power-Modules');
    }
}
```

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
            Route::get('/users', UserController::class, 'index'),
            Route::get('/users/{id}', UserController::class, 'show'),
            
            // Protected user routes
            Route::post('/users', UserController::class, 'create')
                ->addMiddleware(AuthMiddleware::class),
            Route::put('/users/{id}', UserController::class, 'update')
                ->addMiddleware(AuthMiddleware::class),
                
            // Admin-only routes
            Route::delete('/users/{id}', UserController::class, 'delete')
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
            Route::get('/login', AuthController::class, 'showLogin'),
            Route::post('/login', AuthController::class, 'login'),
            
            // Protected routes - auth required
            Route::get('/profile', UserController::class, 'profile')
                ->addMiddleware(AuthMiddleware::class),
            
            // Admin routes - auth + admin permissions
            Route::get('/admin/dashboard', AdminController::class, 'dashboard')
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
            Route::get('/products', ProductController::class, 'index')
                ->addMiddleware(new CacheMiddleware($this->cache, 3600)),
            
            // Don't cache mutations
            Route::post('/products', ProductController::class, 'create'),
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
            ->withPowerSetup(new RoutingSetup())
            ->withModules(RouterModule::class, UserModule::class)
            ->build();
        
        $router = $app->get(ModularRouterInterface::class);
        
        $request = new ServerRequest('GET', '/user/profile');
        $response = $router->handle($request);
        
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