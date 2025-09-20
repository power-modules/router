<?php

namespace Modular\Router\Test\Unit;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use League\Route\Strategy\JsonStrategy;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Router;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAModule;
use Modular\Router\Test\Unit\Sample\LibraryA\ModuleMiddlewareA;
use Modular\Router\Test\Unit\Sample\LibraryA\RouteMiddlewareA;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    public function testRouterCanRegisterPowerModules(): void
    {
        $router = $this->getRouter([LibraryAModule::class]);

        $response = $router->handle($this->getRequest('/library-a/feature-a'));
        $response->getBody()->rewind();

        self::assertSame(
            json_encode(['data' => 'Modular Framework is awesome!']),
            $response->getBody()->getContents(),
        );
    }

    public function testRouterCanRegisterRouteMiddleware(): void
    {
        $router = $this->getRouter([LibraryAModule::class]);
        $response = $router->handle($this->getRequest('/library-a/feature-b'));
        $response->getBody()->rewind();

        self::assertSame(
            json_encode(['attribute-from-middleware' => RouteMiddlewareA::ATTRIBUTE_FROM_MIDDLEWARE_VALUE]),
            $response->getBody()->getContents(),
        );
    }

    public function testRouterCanRegisterModuleMiddleware(): void
    {
        $router = $this->getRouter([LibraryAModule::class]);
        $response = $router->handle($this->getRequest('/library-a/feature-c'));
        $response->getBody()->rewind();

        self::assertSame(
            json_encode(['header-from-middleware' => [ModuleMiddlewareA::HEADER_FROM_MIDDLEWARE_VALUE]]),
            $response->getBody()->getContents(),
        );
    }

    private function getRequest(string $endpoint, string $type = 'GET'): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($type, sprintf('http://localhost/%s', ltrim($endpoint, '/')));
    }

    /**
     * @param array<class-string<PowerModule>> $modules
     */
    private function getRouter(array $modules): ModularRouterInterface
    {
        $router = new Router(
            new JsonStrategy(new ResponseFactory()),
        );
        $rootContainer = new ConfigurableContainer();
        $moduleContainer = new ConfigurableContainer();

        foreach ($modules as $moduleName) {
            /** @var PowerModule $powerModule */
            $powerModule = new $moduleName();
            $powerModule->register($moduleContainer);
            $router->registerPowerModuleRoutes($powerModule, $moduleContainer, null);
            $rootContainer->set($moduleName, $moduleContainer);
        }

        return $router;
    }
}
