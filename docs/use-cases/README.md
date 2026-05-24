# Use Cases & Examples

The Modular Router's flexible architecture supports a wide range of HTTP routing scenarios. Here are real-world examples across different domains.

## 📂 Examples by Domain

### **Complete Examples**
- [Web API with Authentication](web-api.md) - REST API with JWT auth, middleware, and modular boundaries
- [Microservice Router](microservice-router.md) - Service routing with module-based boundaries
- [Plugin Architecture](plugin-architecture.md) - Extensible routing for plugin-based applications

### **Additional Use Case Ideas**

**Web Applications**: Multi-tenant routing with tenant isolation • API gateways with service discovery • Admin panels with role-based routing

**Microservices**: Service mesh routing • Gateway aggregation • Inter-service communication

**Plugin Systems**: CMS routing extensions • E-commerce module routing • Dashboard widget APIs

**Development Tools**: Development server routing • API mocking services • Testing harnesses

## 🎯 Common Patterns

### **Layered API Architecture**
```php
// Infrastructure Layer
class DatabaseModule implements PowerModule, ExportsComponents
{
    public static function exports(): array {
        return [UserRepository::class, OrderRepository::class];
    }
}

// Business Layer  
class BusinessModule implements PowerModule, ImportsComponents, ExportsComponents
{
    public static function imports(): array {
        return [ImportItem::create(DatabaseModule::class, UserRepository::class)];
    }
    
    public static function exports(): array {
        return [UserService::class, OrderService::class];
    }
}

// API Layer
class ApiModule implements PowerModule, ImportsComponents, HasRoutes, HasCustomRouteSlug
{
    public static function imports(): array {
        return [ImportItem::create(BusinessModule::class, UserService::class)];
    }
    
    public function getRouteSlug(): string {
        return '/api/v1';
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/users', ListUsersHandler::class),
            Route::post('/users', CreateUserHandler::class),
        ];
    }
}
```

### **Plugin-Based Routing**
```php
// Core system with extensible routing
class CoreModule implements PowerModule, ExportsComponents, HasRoutes
{
    public static function exports(): array {
        return [PluginManager::class, EventBus::class];
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/health', HealthController::class),
            Route::get('/plugins', ListPluginsHandler::class),
        ];
    }
}

// Plugin modules with their own routes
class BlogPluginModule implements PowerModule, ImportsComponents, HasRoutes
{
    public static function imports(): array {
        return [ImportItem::create(CoreModule::class, PluginManager::class)];
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/posts', ListPostsHandler::class),
            Route::post('/posts', CreatePostHandler::class),
        ];
    }
}

class ShopPluginModule implements PowerModule, ImportsComponents, HasRoutes
{
    public static function imports(): array {
        return [ImportItem::create(CoreModule::class, EventBus::class)];
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/products', ListProductsHandler::class),
            Route::post('/orders', CreateOrderHandler::class),
        ];
    }
}
```

### **Microservice Boundaries**
```php
// User Service. Could become User HTTP microservice
class UserModule implements PowerModule, ExportsComponents, HasRoutes
{
    public static function exports(): array {
        return [UserService::class];
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/users/{id}', ShowUserHandler::class),
            Route::put('/users/{id}', UpdateUserHandler::class),
        ];
    }
}

// Order Service. Could become Order HTTP microservice, calling User HTTP API
class OrderModule implements PowerModule, ImportsComponents, HasRoutes
{
    public static function imports(): array {
        return [ImportItem::create(UserModule::class, UserService::class)];
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/orders', ListOrdersHandler::class),
            Route::post('/orders', CreateOrderHandler::class),
        ];
    }
}
```

## 🔧 Development Patterns

### **Environment-Specific Modules**
```php
// Development routes (only loaded in dev)
class DevModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array {
        return [
            Route::get('/debug/routes', ShowRoutesDebugHandler::class),
            Route::get('/debug/container', ShowContainerDebugHandler::class),
            Route::post('/debug/reset', ResetDebugStateHandler::class),
        ];
    }
}

// Production monitoring
class MonitoringModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string {
        return '/_internal';
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/health', HealthController::class),
            Route::get('/metrics', MetricsController::class),
        ];
    }
}

// Conditional loading
$modules = [RoutingModule::class, RouterModule::class, CoreModule::class];
if ($env === 'development') {
    $modules[] = DevModule::class;
}
if ($env === 'production') {
    $modules[] = MonitoringModule::class;
}

$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(...RoutingSetup::withDefaults())
    ->withModules(...$modules)
    ->build();
```

### **Testing Strategies**
```php
// Unit tests - test route modules in isolation
class UserModuleTest extends TestCase 
{
    public function testUserRoutes()
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withPowerSetup(...RoutingSetup::withDefaults())
            ->withModules(
                RoutingModule::class,
                RouterModule::class,
                UserModule::class,
                MockDatabaseModule::class, // Mock dependencies
            )
            ->build();
        
        $httpEntrypoint = $app->get(\Modular\Router\Contract\HttpEntrypointInterface::class);
        
        $request = new ServerRequest('GET', '/user/profile');
        $response = $httpEntrypoint->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}

// Integration tests - test module interactions
class UserOrderIntegrationTest extends TestCase
{
    public function testUserCanPlaceOrder()
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withPowerSetup(...RoutingSetup::withDefaults())
            ->withModules(
                RoutingModule::class,
                RouterModule::class,
                UserModule::class,
                OrderModule::class,
                DatabaseModule::class, // Real database for integration
            )
            ->build();
        
        $httpEntrypoint = $app->get(\Modular\Router\Contract\HttpEntrypointInterface::class);
        
        // Test cross-module route interactions
        $loginRequest = new ServerRequest('POST', '/user/login');
        $orderRequest = new ServerRequest('POST', '/order/create');
        
        // Verify routing works across modules
    }
}
```

### **Middleware Patterns**
```php
// Global middleware via RouterModule configuration
class SecurityModule implements PowerModule, ExportsComponents
{
    public static function exports(): array {
        return [SecurityMiddleware::class, CorsMiddleware::class];
    }
}

// Module-specific middleware
class ApiModule implements PowerModule, HasRoutes, HasMiddleware, ImportsComponents
{
    public static function imports(): array {
        return [ImportItem::create(SecurityModule::class, SecurityMiddleware::class)];
    }
    
    public function getMiddleware(): array {
        return [
            SecurityMiddleware::class,  // Applied to all routes in this module
            RateLimitMiddleware::class,
        ];
    }
    
    public function getRoutes(): array {
        return [
            Route::get('/public', PublicController::class),
            Route::post('/admin', AdminController::class)
                ->addMiddleware(AdminAuthMiddleware::class), // Additional per-route middleware
        ];
    }
}
```

## 📚 Next Steps

Choose an example that matches your use case:
- **Building APIs?** Start with [Web API example](web-api.md)
- **Creating microservices?** Check out [Microservice Router](microservice-router.md)
- **Need extensibility?** Explore [Plugin Architecture](plugin-architecture.md)
- **Want advanced patterns?** See [Advanced Patterns](../advanced-patterns.md)