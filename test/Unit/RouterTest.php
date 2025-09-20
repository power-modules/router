<?php

namespace Modular\Router\Test\Unit;

use InvalidArgumentException;
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
use Modular\Router\Test\Unit\Sample\LibraryC\LibraryCModule;
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

    public function testRouterThrowsExceptionForUnknownMiddleware(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware Modular\Router\Test\Unit\Sample\LibraryA\RouteMiddlewareA not found in router or module container');

        $router = $this->getRouter([LibraryCModule::class]);
        $router->handle($this->getRequest('/library-c/no-middleware', 'POST'));
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
            $router->registerPowerModuleRoutes($powerModule, $moduleContainer);
            $rootContainer->set($moduleName, $moduleContainer);
        }

        return $router;
    }
}
