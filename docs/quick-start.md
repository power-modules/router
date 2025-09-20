# Quick Start: Integrate Router with the Framework

This guide shows how to wire the router into a Power Modules app using the built-in RoutingSetup and RouterModule.

## Install

```sh
composer require power-modules/router
# Optionally, choose a PSR-7 emitter (example):
composer require laminas/laminas-httphandlerrunner
```

## Bootstrap

```php
# /app/public/test.php
<?php

declare(strict_types=1);

namespace Test;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Modular\Framework\App\ModularAppBuilder;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HasRoutes;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\Route;
use Modular\Router\RouterModule;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/../vendor/autoload.php';

final readonly class StatusController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['status' => 'ok']);
    }
}

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

$app = new ModularAppBuilder(__DIR__)->build();
$app->addPowerModuleSetup(new RoutingSetup());
$app->registerModules([
    RouterModule::class,
    HealthModule::class,
]);

/** @var ModularRouterInterface $router */
$router = $app->get(ModularRouterInterface::class);

// Handle an HTTP request (PSR-7) and emit response
$request = new ServerRequestFactory()->createServerRequest('GET', '/health/status');
$response = $router->handle($request);
(new SapiEmitter())->emit($response);
```

## Notes
- Route prefixes:
  - Automatic: `FooBarModule` → `/foo-bar`
  - Custom: implement `HasCustomRouteSlug::getRouteSlug()` and return a leading-slash path (no trailing slash), e.g. `/api/v1`
- Controllers default to `handle()` when method is omitted.
- Middleware are resolved from the module container first, then from the router’s container.