<?php

namespace Modular\Router\Test\Unit;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use League\Route\Strategy\JsonStrategy;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Framework\Container\Exception\ServiceDefinitionNotFound;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Router;
use Modular\Router\Test\Unit\Sample\LibraryA\LibraryAController;
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
        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryAModule::class]);

        self::assertSame(
            json_encode(LibraryAController::HANDLE_RESPONSE),
            (string) $router->handle($this->getRequest('/library-a/feature-a'))->getBody(),
        );
    }

    public function testRouterCanRegisterRouteMiddleware(): void
    {
        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryAModule::class]);

        self::assertSame(
            json_encode(['attribute-from-middleware' => RouteMiddlewareA::ATTRIBUTE_FROM_MIDDLEWARE_VALUE]),
            (string) $router->handle($this->getRequest('/library-a/feature-b'))->getBody(),
        );
    }

    public function testRouterCanRegisterModuleMiddleware(): void
    {
        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryAModule::class]);

        self::assertSame(
            json_encode(['header-from-middleware' => [ModuleMiddlewareA::HEADER_FROM_MIDDLEWARE_VALUE]]),
            (string) $router->handle($this->getRequest('/library-a/feature-c'))->getBody(),
        );
    }

    public function testRouterCanRegisterRouteResponseDecorators(): void
    {
        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryAModule::class]);
        $response = $router->handle($this->getRequest('/library-a/feature-a'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Route'));

        $response = $router->handle($this->getRequest('/library-a/feature-b'));
        self::assertSame('', $response->getHeaderLine('X-Library-A-Route'));
    }

    public function testRouterCanRegisterModuleResponseDecorators(): void
    {
        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryAModule::class]);
        $response = $router->handle($this->getRequest('/library-a/feature-a'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Static'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Closure'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Basic'));

        $response = $router->handle($this->getRequest('/library-a/feature-b'));
        self::assertSame('', $response->getHeaderLine('X-Library-A-Route'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Static'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Closure'));
        self::assertSame('true', $response->getHeaderLine('X-Library-A-Basic'));
    }

    public function testRouterThrowsExceptionForUnknownMiddleware(): void
    {
        $this->expectException(ServiceDefinitionNotFound::class);
        $this->expectExceptionMessage('Service definition with id "Modular\Router\Test\Unit\Sample\LibraryA\RouteMiddlewareA" was not found.');

        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryCModule::class]);
        $router->handle($this->getRequest('/already-has-slash/no-middleware', 'GET'));
    }

    private function getRequest(string $endpoint, string $type = 'GET'): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($type, sprintf('http://localhost/%s', ltrim($endpoint, '/')));
    }

    /**
     * @param array<class-string<PowerModule>> $modules
     */
    private function getRouter(ConfigurableContainer $rootContainer, array $modules): ModularRouterInterface
    {
        $router = new Router(
            new JsonStrategy(new ResponseFactory()),
        );

        foreach ($modules as $moduleName) {
            /** @var PowerModule $powerModule */
            $powerModule = new $moduleName();
            $moduleContainer = new ConfigurableContainer();
            $powerModule->register($moduleContainer);
            $router->registerPowerModuleRoutes($powerModule, $moduleContainer);
            $rootContainer->set($moduleName, $moduleContainer);
        }

        $rootContainer->set(ModularRouterInterface::class, $router);

        return $router;
    }
}
