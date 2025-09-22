# Module Composition Patterns

Advanced patterns for organizing and composing modules in large-scale applications.

## Hierarchical Module Organization

Structure complex applications with clear layers and dependencies.

## Infrastructure Modules

Base-level modules that provide core services:

```php
// Database infrastructure
final readonly class DatabaseModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            DatabaseConnection::class,
            QueryBuilder::class,
            TransactionManager::class,
        ];
    }
}

// Caching infrastructure
final readonly class CacheModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            CacheInterface::class, // Redis, Memcached, etc.
        ];
    }
}

// Logging infrastructure
final readonly class LoggingModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            LoggerInterface::class,
            AuditLogger::class,
        ];
    }
}
```

## Domain Modules

Business logic modules that consume infrastructure:

```php
// User domain
final readonly class UserModule implements PowerModule, ImportsComponents, ExportsComponents, HasRoutes
{
    public static function imports(): array
    {
        return [
            ImportItem::create(DatabaseModule::class, DatabaseConnection::class),
            ImportItem::create(CacheModule::class, CacheInterface::class),
            ImportItem::create(LoggingModule::class, LoggerInterface::class),
        ];
    }
    
    public static function exports(): array
    {
        return [
            UserService::class,
            UserRepository::class,
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/', UserController::class, 'index'),
            Route::post('/', UserController::class, 'create'),
            Route::get('/{id}', UserController::class, 'show'),
            Route::put('/{id}', UserController::class, 'update'),
            Route::delete('/{id}', UserController::class, 'delete'),
        ];
    }
}

// Order domain (depends on User domain)
final readonly class OrderModule implements PowerModule, ImportsComponents, ExportsComponents, HasRoutes
{
    public static function imports(): array
    {
        return [
            ImportItem::create(DatabaseModule::class, DatabaseConnection::class),
            ImportItem::create(UserModule::class, UserService::class),
            ImportItem::create(LoggingModule::class, AuditLogger::class),
        ];
    }
    
    public static function exports(): array
    {
        return [
            OrderService::class,
            PaymentService::class,
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/', OrderController::class, 'index'),
            Route::post('/', OrderController::class, 'create'),
            Route::get('/{id}', OrderController::class, 'show'),
            Route::post('/{id}/payment', PaymentController::class, 'process'),
        ];
    }
}
```

## API Aggregation Modules

High-level modules that compose domain modules into APIs:

```php
// API v1 aggregation
final readonly class ApiV1Module implements PowerModule, ImportsComponents, HasRoutes, HasCustomRouteSlug, HasMiddleware
{
    public static function imports(): array
    {
        return [
            ImportItem::create(UserModule::class, UserService::class),
            ImportItem::create(OrderModule::class, OrderService::class),
        ];
    }
    
    public function getRouteSlug(): string
    {
        return '/api/v1';
    }
    
    public function getMiddleware(): array
    {
        return [
            ApiVersionMiddleware::class,
            RateLimitMiddleware::class,
            CorsMiddleware::class,
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/health', HealthController::class),
            Route::get('/info', ApiInfoController::class),
            // User and Order routes are automatically prefixed as /api/v1/users/... and /api/v1/orders/...
        ];
    }
}

// API v2 with breaking changes
final readonly class ApiV2Module implements PowerModule, ImportsComponents, HasRoutes, HasCustomRouteSlug, HasMiddleware
{
    public static function imports(): array
    {
        return [
            ImportItem::create(UserModule::class, UserService::class),
            ImportItem::create(OrderModule::class, OrderService::class),
        ];
    }
    
    public function getRouteSlug(): string
    {
        return '/api/v2';
    }
    
    public function getMiddleware(): array
    {
        return [
            ApiV2VersionMiddleware::class,
            EnhancedRateLimitMiddleware::class,
            CorsMiddleware::class,
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/health', HealthV2Controller::class),
            Route::get('/status', StatusController::class), // New in v2
            // Different controllers for v2 API changes
        ];
    }
}
```

## Feature Modules

Cross-cutting feature modules:

```php
// Analytics feature
final readonly class AnalyticsModule implements PowerModule, ImportsComponents, HasRoutes, HasMiddleware
{
    public static function imports(): array
    {
        return [
            ImportItem::create(DatabaseModule::class, DatabaseConnection::class),
            ImportItem::create(UserModule::class, UserService::class),
        ];
    }
    
    public function getMiddleware(): array
    {
        return [
            AnalyticsTrackingMiddleware::class, // Tracks all requests
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/dashboard', AnalyticsController::class, 'dashboard'),
            Route::get('/reports/{type}', AnalyticsController::class, 'report'),
        ];
    }
}

// Admin feature
final readonly class AdminModule implements PowerModule, ImportsComponents, HasRoutes, HasCustomRouteSlug, HasMiddleware
{
    public static function imports(): array
    {
        return [
            ImportItem::create(UserModule::class, UserService::class),
            ImportItem::create(OrderModule::class, OrderService::class),
            ImportItem::create(AnalyticsModule::class, AnalyticsService::class),
        ];
    }
    
    public function getRouteSlug(): string
    {
        return '/admin';
    }
    
    public function getMiddleware(): array
    {
        return [
            AuthMiddleware::class,
            AdminAuthMiddleware::class,
            AuditMiddleware::class,
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/dashboard', AdminController::class, 'dashboard'),
            Route::get('/users', AdminUserController::class, 'index'),
            Route::get('/orders', AdminOrderController::class, 'index'),
            Route::get('/analytics', AdminAnalyticsController::class, 'dashboard'),
        ];
    }
}
```

## Environment-Specific Modules

```php
// Development tools
final readonly class DevToolsModule implements PowerModule, HasRoutes, HasCustomRouteSlug
{
    public function getRouteSlug(): string
    {
        return '/dev';
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/debug', DebugController::class, 'info'),
            Route::get('/profiler', ProfilerController::class, 'show'),
            Route::post('/test-data', TestDataController::class, 'create'),
            Route::delete('/test-data', TestDataController::class, 'clear'),
        ];
    }
}

// Production monitoring
final readonly class MonitoringModule implements PowerModule, HasRoutes, HasCustomRouteSlug, HasMiddleware
{
    public function getRouteSlug(): string
    {
        return '/monitor';
    }
    
    public function getMiddleware(): array
    {
        return [
            MonitoringAuthMiddleware::class,
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            Route::get('/health', HealthCheckController::class, 'detailed'),
            Route::get('/metrics', MetricsController::class, 'prometheus'),
            Route::get('/logs', LogController::class, 'tail'),
        ];
    }
}
```

## Conditional Module Loading

Load different module compositions based on environment:

```php
final readonly class EnvironmentModuleLoader
{
    public function __construct(private readonly Config $config)
    {
    }

    public function getModulesForEnvironment(string $env): array
    {
        // Base modules for all environments
        $modules = [
            RouterModule::class,
            DatabaseModule::class,
            CacheModule::class,
            LoggingModule::class,
            UserModule::class,
            OrderModule::class,
        ];
        
        // API versions
        $modules[] = ApiV1Module::class;
        if ($this->isApiV2Enabled()) {
            $modules[] = ApiV2Module::class;
        }
        
        // Features
        if ($this->isAnalyticsEnabled()) {
            $modules[] = AnalyticsModule::class;
        }
        
        if ($this->isAdminEnabled()) {
            $modules[] = AdminModule::class;
        }
        
        // Environment-specific modules
        match ($env) {
            'development' => $modules[] = DevToolsModule::class,
            'production' => $modules[] = MonitoringModule::class,
            'testing' => array_push($modules, TestingModule::class, MockDataModule::class),
        };
        
        return $modules;
    }
    
    private function isApiV2Enabled(): bool
    {
        return $this->config->get(Setting::ApiV2Enabled) === true;
    }
    
    private function isAnalyticsEnabled(): bool
    {
        return $this->config->get(Setting::AnalyticsEnabled) === true;
    }
    
    private function isAdminEnabled(): bool
    {
        return $this->config->get(Setting::AdminEnabled) === true;
    }
}
```

## Application Assembly

```php
// Bootstrap application with environment-specific modules
$env = $_ENV['APP_ENV'] ?? 'development';
$loader = new EnvironmentModuleLoader();
$modules = $loader->getModulesForEnvironment($env);

$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(new RoutingSetup())
    ->withModules(...$modules)
    ->build();
```

## Testing Module Composition

```php
class ModuleCompositionTest extends TestCase
{
    public function testDevelopmentEnvironment(): void
    {
        $loader = new EnvironmentModuleLoader();
        $modules = $loader->getModulesForEnvironment('development');
        
        $this->assertContains(DevToolsModule::class, $modules);
        $this->assertNotContains(MonitoringModule::class, $modules);
    }
    
    public function testProductionEnvironment(): void
    {
        $loader = new EnvironmentModuleLoader();
        $modules = $loader->getModulesForEnvironment('production');
        
        $this->assertContains(MonitoringModule::class, $modules);
        $this->assertNotContains(DevToolsModule::class, $modules);
    }
    
    public function testModuleDependencies(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withPowerSetup(new RoutingSetup())
            ->withModules(
                RouterModule::class,
                DatabaseModule::class,
                UserModule::class,
                OrderModule::class, // Depends on UserModule
            )
            ->build();
        
        $router = $app->get(ModularRouterInterface::class);
        
        // Test that order creation can access user services
        $request = new ServerRequest('POST', '/orders', [], json_encode([
            'user_id' => 1,
            'items' => [['product' => 'widget', 'quantity' => 2]]
        ]));
        
        $response = $router->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
    }
}
```

## Benefits and Patterns

### Layered Architecture Benefits
- **Clear separation of concerns** between infrastructure, domain, and API layers
- **Reusable components** that can be shared across different APIs
- **Easy testing** with clear module boundaries
- **Flexible deployment** with environment-specific module loading

### Dependency Management
- Use `ImportsComponents` and `ExportsComponents` for explicit dependencies
- Infrastructure modules provide foundational services
- Domain modules contain business logic
- API modules aggregate domain services

### Versioning Strategy
- Separate modules for different API versions
- Share domain modules across API versions
- Use custom route slugs for version prefixes
- Version-specific middleware and controllers

## Best Practices

1. **Layer modules logically** - infrastructure → domain → API → features
2. **Make dependencies explicit** through imports/exports
3. **Keep modules focused** on single responsibilities
4. **Use environment loading** for deployment flexibility
5. **Test module interactions** to verify dependency injection
6. **Document module relationships** for team understanding