# Web API with Authentication

Build a REST API with modular authentication using automatic route discovery, JWT tokens, and PSR-15 middleware - all with proper module encapsulation.

## Quick Start

```bash
composer require power-modules/router
composer require laminas/laminas-diactoros laminas/laminas-httphandlerrunner
composer require firebase/php-jwt  # For JWT tokens
```

**Architecture**: `AuthModule` exports JWT services → `ApiModule` imports auth and defines routes → `RouterModule` wires everything together.

## Implementation

### 1. Authentication Module

```php
<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\Diactoros\Response\JsonResponse;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// JWT Service for token management
final readonly class JwtManager
{
    public function __construct(private string $secretKey) {}
    
    public function generateToken(string $userId): string
    {
        $payload = [
            'user_id' => $userId,
            'exp' => time() + 3600, // 1 hour
            'iat' => time(),
        ];
        
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }
    
    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return (array) $decoded;
        } catch (\Exception) {
            return null;
        }
    }
}

// Authentication service
final readonly class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtManager $jwtManager
    ) {}
    
    public function authenticate(string $username, string $password): ?string
    {
        $user = $this->userRepository->findByUsername($username);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return $this->jwtManager->generateToken($user['id']);
        }
        
        return null;
    }
    
    public function getUserFromToken(string $token): ?array
    {
        $payload = $this->jwtManager->validateToken($token);
        
        if ($payload && isset($payload['user_id'])) {
            return $this->userRepository->findById($payload['user_id']);
        }
        
        return null;
    }
}

// Authentication middleware
final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $authService) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Authorization header required'], 401);
        }
        
        $token = substr($authHeader, 7);
        $user = $this->authService->getUserFromToken($token);
        
        if (!$user) {
            return new JsonResponse(['error' => 'Invalid or expired token'], 401);
        }

        // Add user to request attributes for controllers
        $request = $request->withAttribute('user', $user);
        
        return $handler->handle($request);
    }
}

// Simple user repository (in real app, use database)
final readonly class UserRepository
{
    private array $users;
    
    public function __construct()
    {
        $this->users = [
            ['id' => '1', 'username' => 'admin', 'password_hash' => password_hash('password', PASSWORD_DEFAULT)],
            ['id' => '2', 'username' => 'user', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ];
    }
    
    public function findByUsername(string $username): ?array
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }
        return null;
    }
    
    public function findById(string $id): ?array
    {
        foreach ($this->users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }
}

// Auth module
final readonly class AuthModule implements PowerModule, ExportsComponents
{
    public static function exports(): array
    {
        return [
            AuthService::class,
            AuthMiddleware::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(UserRepository::class, UserRepository::class);
        
        $container->set(JwtManager::class, JwtManager::class)
            ->addArguments(['my-secret-key-change-in-production']);
        
        $container->set(AuthService::class, AuthService::class)
            ->addArguments([UserRepository::class, JwtManager::class]);
        
        $container->set(AuthMiddleware::class, AuthMiddleware::class)
            ->addArguments([AuthService::class]);
            
        $container->set(AuthController::class, AuthController::class)
            ->addArguments([AuthService::class]);
    }
}
```

### 2. API Module with Router Integration

```php
// Auth controller
final readonly class AuthController implements RequestHandlerInterface
{
    public function __construct(private AuthService $authService) {}
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getMethod()) {
            'POST' => $this->login($request),
            default => new JsonResponse(['error' => 'Method not allowed'], 405),
        };
    }
    
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';
        
        if (!$username || !$password) {
            return new JsonResponse(['error' => 'Username and password required'], 400);
        }
        
        $token = $this->authService->authenticate($username, $password);
        
        if (!$token) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        return new JsonResponse(['token' => $token]);
    }
}

// User controller
final readonly class UserController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getMethod()) {
            'GET' => $this->profile($request),
            default => new JsonResponse(['error' => 'Method not allowed'], 405),
        };
    }
    
    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        
        return new JsonResponse([
            'id' => $user['id'],
            'username' => $user['username'],
        ]);
    }
}

// Product controller
final readonly class ProductController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getMethod()) {
            'GET' => $this->list($request),
            'POST' => $this->create($request),
            default => new JsonResponse(['error' => 'Method not allowed'], 405),
        };
    }
    
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        // Demo data
        $products = [
            ['id' => 1, 'name' => 'Widget A', 'price' => 29.99],
            ['id' => 2, 'name' => 'Widget B', 'price' => 39.99],
        ];
        
        return new JsonResponse($products);
    }
    
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $name = $body['name'] ?? '';
        $price = $body['price'] ?? 0;
        
        if (!$name || !is_numeric($price) || $price <= 0) {
            return new JsonResponse(['error' => 'Name and valid price required'], 400);
        }

        // In real app, save to database
        return new JsonResponse([
            'id' => rand(1000, 9999),
            'name' => $name,
            'price' => $price
        ], 201);
    }
}

// API Module with Router Contracts
final readonly class ApiModule implements PowerModule, ImportsComponents, HasRoutes, HasCustomRouteSlug
{
    public static function imports(): array
    {
        return [
            ImportItem::create(AuthModule::class, AuthService::class, AuthMiddleware::class),
        ];
    }

    public function getRouteSlug(): string
    {
        return '/api/v1'; // Custom prefix instead of auto-generated '/api'
    }

    public function getRoutes(): array
    {
        return [
            // Public login route (no middleware) - uses AuthController
            Route::post('/auth/login', AuthController::class, 'login'),
            
            // Protected routes (with AuthMiddleware)
            Route::get('/user/profile', UserController::class, 'profile')
                ->addMiddleware(AuthMiddleware::class),
            Route::get('/products', ProductController::class, 'list')
                ->addMiddleware(AuthMiddleware::class),
            Route::post('/products', ProductController::class, 'create')
                ->addMiddleware(AuthMiddleware::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(AuthController::class, AuthController::class)
            ->addArguments([AuthService::class]);
        $container->set(UserController::class, UserController::class);
        $container->set(ProductController::class, ProductController::class);
    }
}
```

### 3. Application Bootstrap

```php
<?php

declare(strict_types=1);

// public/index.php

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\RouterModule;

// Build the modular application with router
$app = new ModularAppBuilder(__DIR__ . '/..')
    ->withPowerSetup(new RoutingSetup())  // Wires modules with HasRoutes automatically
    ->withModules(
        RouterModule::class,  // Provides ModularRouterInterface
        AuthModule::class,    // Provides authentication services
        ApiModule::class,     // Implements HasRoutes with our API endpoints
    )
    ->build();

// Get the router (configured with all module routes)
$router = $app->get(ModularRouterInterface::class);

// Handle incoming HTTP request
$request = ServerRequestFactory::fromGlobals();
$response = $router->handle($request);

// Emit response
(new SapiEmitter())->emit($response);
```

### 4. Optional: Router Configuration

```php
// config/modular_router.php
<?php

declare(strict_types=1);

use Laminas\Diactoros\ResponseFactory;
use League\Route\Strategy\JsonStrategy;
use Modular\Router\Config\Config;
use Modular\Router\Config\Setting;

// Use JSON strategy for automatic JSON responses
return Config::create()
    ->set(Setting::Strategy, new JsonStrategy(new ResponseFactory()));
```

## Key Features

- **Module Encapsulation**: Controllers resolve from their own DI containers, not global
- **Automatic Route Discovery**: `HasRoutes` interface enables zero-config routing
- **PSR-15 Middleware**: Per-route authentication and request/response flow
- **Custom Route Prefixes**: `HasCustomRouteSlug` for API versioning (`/api/v1`)

```php
// Routes discovered automatically from modules
class ApiModule implements HasRoutes {
    public function getRoutes(): array {
        return [
            Route::get('/products', ProductController::class)
                ->addMiddleware(AuthMiddleware::class),
        ];
    }
}
```

## Usage Examples

### 1. Login to get a token:
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "password"}'

# Response: {"token": "eyJ0eXAiOiJKV1QiLCJhbGci..."}
```

### 2. Access protected endpoints:
```bash
# Get user profile
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/v1/user/profile

# List products  
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/v1/products

# Create product
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "New Widget", "price": 49.99}'
```

## Routes Created

The application creates these routes automatically:
- `POST /api/v1/auth/login` (public)
- `GET /api/v1/user/profile` (requires auth)
- `GET /api/v1/products` (requires auth)
- `POST /api/v1/products` (requires auth)

## Next Steps

**Extend the API**: Add controllers, swap JWT for OAuth2, integrate databases through repositories.

**Production**: Replace demo JWT with proper secret management, add password hashing validation, implement logging middleware.

The modular design lets you evolve each concern independently while maintaining clean boundaries.