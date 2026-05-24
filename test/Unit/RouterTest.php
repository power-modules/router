<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Framework\Container\Exception\ServiceDefinitionNotFound;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Response\ResponseDecoratorChain;
use Modular\Router\Response\SyntheticResponseFactory;
use Modular\Router\Router;
use Modular\Router\Test\Unit\Sample\AmbiguousRoutes\AmbiguousRoutesModule;
use Modular\Router\Test\Unit\Sample\DisambiguatedDynamicRoutes\DisambiguatedDynamicRoutesModule;
use Modular\Router\Test\Unit\Sample\DispatchContract\DispatchContractModule;
use Modular\Router\Test\Unit\Sample\DuplicateRoutes\DuplicateRoutesModule;
use Modular\Router\Test\Unit\Sample\DynamicRoute\DynamicRouteModule;
use Modular\Router\Test\Unit\Sample\InvalidHandler\InvalidHandlerModule;
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

    public function testRouterPropagatesUnknownMiddlewareResolutionExceptions(): void
    {
        $this->expectException(ServiceDefinitionNotFound::class);

        $rootContainer = new ConfigurableContainer();
        $router = $this->getRouter($rootContainer, [LibraryCModule::class]);

        $router->handle($this->getRequest('/already-has-slash/no-middleware', 'GET'));
    }

    public function testRouterRejectsDuplicateRoutesDuringRegistration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route registration for [GET] /duplicate-routes/users');

        $module = new DuplicateRoutesModule();
        $moduleContainer = new ConfigurableContainer();
        $module->register($moduleContainer);

        $router = new Router(
            new SyntheticResponseFactory(new ResponseFactory()),
            new ResponseDecoratorChain(),
        );

        $router->registerPowerModuleRoutes($module, $moduleContainer);
    }

    public function testRouterCanRegisterBasicDynamicPlaceholderRoute(): void
    {
        $module = new DynamicRouteModule();
        $moduleContainer = new ConfigurableContainer();
        $module->register($moduleContainer);

        $router = new Router(
            new SyntheticResponseFactory(new ResponseFactory()),
            new ResponseDecoratorChain(),
        );

        $router->registerPowerModuleRoutes($module, $moduleContainer);

        self::assertSame(
            json_encode(['id' => '123']),
            (string) $router->handle($this->getRequest('/dynamic-route/users/123'))->getBody(),
        );
    }

    public function testStaticRouteTakesPrecedenceOverDynamicRoute(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        self::assertSame(
            json_encode(['route' => 'static', 'attributes' => []]),
            (string) $router->handle($this->getRequest('/dispatch-contract/users/all'))->getBody(),
        );
    }

    public function testRouterPropagatesLazyCompilationExceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ambiguous dynamic route registration for [GET] /ambiguous-routes/reports/{slug}');

        $module = new AmbiguousRoutesModule();
        $moduleContainer = new ConfigurableContainer();
        $module->register($moduleContainer);

        $router = new Router(
            new SyntheticResponseFactory(new ResponseFactory()),
            new ResponseDecoratorChain(),
        );

        $router->registerPowerModuleRoutes($module, $moduleContainer);

        $router->handle($this->getRequest('/ambiguous-routes/reports/2026'));
    }

    public function testRouterAllowsDisambiguatedDynamicRoutesWithDifferentPlaceholderNames(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DisambiguatedDynamicRoutesModule::class]);

        self::assertSame(
            json_encode(['attributes' => ['year' => '2026']]),
            (string) $router->handle($this->getRequest('/disambiguated-dynamic-routes/reports/2026/summary'))->getBody(),
        );

        self::assertSame(
            json_encode(['attributes' => ['slug' => 'annual-review']]),
            (string) $router->handle($this->getRequest('/disambiguated-dynamic-routes/reports/annual-review/details'))->getBody(),
        );
    }

    public function testRouterPreservesExactTrailingSlashBehavior(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        self::assertSame(404, $router->handle($this->getRequest('/dispatch-contract/slash/'))->getStatusCode());
        self::assertSame(200, $router->handle($this->getRequest('/dispatch-contract/slash'))->getStatusCode());
    }

    public function testRouterExecutesModuleMiddlewareBeforeRouteMiddleware(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        $response = $router->handle($this->getRequest('/dispatch-contract/ordered'));

        self::assertSame('module,route', $response->getHeaderLine('X-Middleware-Order'));
    }

    public function testRouterAppliesDecoratorsInGlobalModuleRouteOrder(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);
        $router->addResponseDecorator(
            static fn (\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface => $response->withHeader('X-Decorator-Order', trim($response->getHeaderLine('X-Decorator-Order') . ',global', ',')),
        );

        $response = $router->handle($this->getRequest('/dispatch-contract/ordered'));

        self::assertSame('global,module,route', $response->getHeaderLine('X-Decorator-Order'));
    }

    public function testSyntheticResponsesApplyGlobalDecoratorsOnly(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);
        $router->addResponseDecorator(
            static fn (\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface => $response->withHeader('X-Decorator-Order', trim($response->getHeaderLine('X-Decorator-Order') . ',global', ',')),
        );

        $response = $router->handle($this->getRequest('/dispatch-contract/missing'));

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
        self::assertSame('global', $response->getHeaderLine('X-Decorator-Order'));
    }

    public function testHeadFallsBackToGet(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        $response = $router->handle($this->getRequest('/dispatch-contract/method-check', 'HEAD'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [
                'method' => 'GET',
                'path' => '/dispatch-contract/method-check',
                'attributes' => [],
                'headers' => [
                    'X-Middleware-Order' => ['module'],
                ],
            ],
            json_decode((string) $response->getBody(), true),
        );
    }

    public function testOptionsReturnsAllowedMethodsForPath(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        $response = $router->handle($this->getRequest('/dispatch-contract/method-check', 'OPTIONS'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('GET, HEAD, OPTIONS, POST', $response->getHeaderLine('Allow'));
    }

    public function testRouterReturnsNotFoundForUnknownPath(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        $response = $router->handle($this->getRequest('/dispatch-contract/missing'));

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

    public function testRouterReturnsMethodNotAllowedForKnownPathWithDifferentMethod(): void
    {
        $router = $this->getRouter(new ConfigurableContainer(), [DispatchContractModule::class]);

        $response = $router->handle($this->getRequest('/dispatch-contract/method-check', 'PATCH'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            [
                'type' => 'about:blank',
                'title' => 'Method Not Allowed',
                'status' => 405,
            ],
            json_decode((string) $response->getBody(), true),
        );
        self::assertSame('GET, HEAD, OPTIONS, POST', $response->getHeaderLine('Allow'));
    }

    public function testRouterPropagatesInvalidResolvedHandlerExceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resolved route handler must implement Psr\\Http\\Server\\RequestHandlerInterface, Modular\\Router\\Test\\Unit\\Sample\\InvalidHandler\\InvalidHandlerController returned.');

        $router = $this->getRouter(new ConfigurableContainer(), [InvalidHandlerModule::class]);

        $router->handle($this->getRequest('/invalid-handler/invalid'));
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
        $responseDecoratorChain = new ResponseDecoratorChain();
        $router = new Router(
            new SyntheticResponseFactory(new ResponseFactory()),
            $responseDecoratorChain,
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
