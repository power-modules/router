# Getting Started

Get up and running with the Modular Router in minutes.

## Installation

Install via Composer:

```bash
composer require power-modules/router
```

### Optional Dependencies

For HTTP handling, pick a PSR-7 implementation and emitter:

```bash
composer require laminas/laminas-diactoros laminas/laminas-httphandlerrunner
```

## Your First Router Module

The simplest way to understand the router is to build a basic module with routes:

```php
<?php

declare(strict_types=1);

use Modular\Framework\App\ModularAppBuilder;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Route;
use Modular\Router\RouterModule;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

require_once __DIR__ . '/vendor/autoload.php';

// Define a simple controller
final readonly class StatusController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['status' => 'ok', 'service' => 'health']);
    }
}

// Define a module that provides routes
final readonly class HealthModule implements PowerModule, HasRoutes
{
    public function getRoutes(): array
    {
        return [
            Route::get('/status', StatusController::class),
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(StatusController::class, StatusController::class);
    }
}

// Build and use the application
$app = new ModularAppBuilder(__DIR__)
    ->withPowerSetup(new RoutingSetup())  // Enables automatic route discovery
    ->withModules(
        RouterModule::class,  // Provides the router service
        HealthModule::class,  // Your module with routes
    )
    ->build();

// Get the router and handle a request
$router = $app->get(ModularRouterInterface::class);

// Example request (in real app, use ServerRequestFactory::fromGlobals())
$request = (new Laminas\Diactoros\ServerRequestFactory())
    ->createServerRequest('GET', '/health/status');

$response = $router->handle($request);

// Emit the response
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
```

## Related Documentation

- **[Architecture Guide](architecture.md)** - Design principles and system integration
- **[Advanced Patterns](advanced-patterns.md)** - Routing patterns and optimization
- **[Use Cases](use-cases/README.md)** - Real-world examples and specialized patterns
- **[API Reference](api-reference.md)** - Complete interface and class documentation
