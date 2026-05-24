<?php

declare(strict_types=1);

namespace Modular\Router\Test;

use Laminas\Diactoros\ServerRequestFactory;
use Modular\Framework\App\Config\Config;
use Modular\Framework\App\Config\Setting;
use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\Contract\HttpEntrypointInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\RouterModule;
use Modular\Router\RoutingModule;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAController;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAModule;
use PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    public function testItExportsComposedHttpEntrypointByDefault(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withConfig(Config::forAppRoot(__DIR__)->set(Setting::CachePath, sys_get_temp_dir()))
            ->withPowerSetup(...RoutingSetup::withDefaults())
            ->withModules(
                RoutingModule::class,
                RouterModule::class,
                LibraryAModule::class,
            )
            ->build()
        ;

        $router = $app->get(HttpEntrypointInterface::class);
        $request = new ServerRequestFactory()->createServerRequest('GET', '/library-a/feature-a');
        $response = $router->handle($request);

        self::assertSame(
            json_encode(LibraryAController::HANDLE_RESPONSE),
            (string) $response->getBody(),
        );

        self::assertSame('true', $response->getHeaderLine('X-Library-A-Static'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Closure'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Basic'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Route'));
    }

    public function testItReturnsProblemDetailsForDefaultNotFoundResponses(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withConfig(Config::forAppRoot(__DIR__)->set(Setting::CachePath, sys_get_temp_dir()))
            ->withPowerSetup(...RoutingSetup::withDefaults())
            ->withModules(
                RoutingModule::class,
                RouterModule::class,
                LibraryAModule::class,
            )
            ->build()
        ;

        $router = $app->get(HttpEntrypointInterface::class);
        $request = new ServerRequestFactory()->createServerRequest('GET', '/missing');
        $response = $router->handle($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            [
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => 404,
            ],
            json_decode((string) $response->getBody(), true),
        );
    }
}
