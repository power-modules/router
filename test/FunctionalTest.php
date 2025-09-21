<?php

declare(strict_types=1);

namespace Modular\Router\Test;

use Laminas\Diactoros\ServerRequestFactory;
use Modular\Framework\App\Config\Config;
use Modular\Framework\App\Config\Setting;
use Modular\Framework\App\ModularAppBuilder;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\PowerModule\Setup\RoutingSetup;
use Modular\Router\RouterModule;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAController;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAModule;
use PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    public function testItCanUseDefaultStrategy(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withConfig(Config::forAppRoot(__DIR__)->set(Setting::CachePath, sys_get_temp_dir()))
            ->build()
        ;
        $app->addPowerModuleSetup(new RoutingSetup());
        $app->registerModules([
            RouterModule::class,
            LibraryAModule::class,
        ]);

        $router = $app->get(ModularRouterInterface::class);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/library-a/feature-a');

        $response = $router->handle($request);
        $response->getBody()->rewind();
        self::assertSame(json_encode(LibraryAController::HANDLE_RESPONSE), $response->getBody()->getContents());
    }
}
