<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Modular\Framework\Container\ConfigurableContainer;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HttpEntrypointInterface;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ModularRouterInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Modular\Router\ExceptionHandlingMiddleware;
use Modular\Router\HttpEntrypoint;
use Modular\Router\Response\ResponseDecoratorChain;
use Modular\Router\Response\SyntheticResponseFactory;
use Modular\Router\Router;
use Modular\Router\Test\Unit\Sample\AmbiguousRoutes\AmbiguousRoutesModule;
use Modular\Router\Test\Unit\Sample\InvalidHandler\InvalidHandlerModule;
use Modular\Router\Test\Unit\Sample\ThrowingFlow\ThrowingDomainException;
use Modular\Router\Test\Unit\Sample\ThrowingFlow\ThrowingFlowModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

#[CoversClass(HttpEntrypoint::class)]
#[CoversClass(ExceptionHandlingMiddleware::class)]
final class HttpEntrypointTest extends TestCase
{
    public function testEntrypointMapsControllerExceptionsWithDefaultProblemDetailsMiddleware(): void
    {
        $entrypoint = $this->getEntrypoint([ThrowingFlowModule::class]);

        $response = $entrypoint->handle($this->getRequest('/throwing-flow/controller'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500],
            json_decode((string) $response->getBody(), true),
        );
    }

    public function testEntrypointMapsRouteMiddlewareExceptionsWithDefaultProblemDetailsMiddleware(): void
    {
        $entrypoint = $this->getEntrypoint([ThrowingFlowModule::class]);

        $response = $entrypoint->handle($this->getRequest('/throwing-flow/route-middleware'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testEntrypointMapsModuleMiddlewareExceptionsWithDefaultProblemDetailsMiddleware(): void
    {
        $entrypoint = $this->getEntrypoint([ThrowingFlowModule::class]);

        $response = $entrypoint->handle($this->getRequest('/throwing-flow/module-middleware'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testEntrypointMapsInvalidResolvedHandlerExceptionsWithDefaultProblemDetailsMiddleware(): void
    {
        $entrypoint = $this->getEntrypoint([InvalidHandlerModule::class]);

        $response = $entrypoint->handle($this->getRequest('/invalid-handler/invalid'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500],
            json_decode((string) $response->getBody(), true),
        );
    }

    public function testEntrypointMapsLazyCompilationExceptionsWithDefaultProblemDetailsMiddleware(): void
    {
        $entrypoint = $this->getEntrypoint([AmbiguousRoutesModule::class]);

        $response = $entrypoint->handle($this->getRequest('/ambiguous-routes/reports/2026'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500],
            json_decode((string) $response->getBody(), true),
        );
    }

    public function testCustomEntrypointMiddlewareCanEmitProblemDetailsForDomainAndUnknownExceptions(): void
    {
        $responseDecoratorChain = new ResponseDecoratorChain();
        $syntheticResponseFactory = new SyntheticResponseFactory(new ResponseFactory());
        $router = $this->getRouter(
            [ThrowingFlowModule::class, InvalidHandlerModule::class],
            $syntheticResponseFactory,
            $responseDecoratorChain,
        );
        $router->addResponseDecorator(
            static fn (ResponseInterface $response): ResponseInterface => $response->withHeader('X-Global-Decorator', 'true'),
        );
        $entrypoint = new HttpEntrypoint(
            new class ($responseDecoratorChain) implements HttpEntrypointMiddlewareInterface {
                private readonly ResponseFactory $responseFactory;

                public function __construct(
                    private readonly ResponseDecoratorChainInterface $responseDecoratorChain,
                ) {
                    $this->responseFactory = new ResponseFactory();
                }

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    try {
                        return $handler->handle($request);
                    } catch (Throwable $throwable) {
                        return $this->responseDecoratorChain->decorateResponse(
                            $this->createProblemDetailsResponse($throwable),
                        );
                    }
                }

                private function createProblemDetailsResponse(Throwable $throwable): ResponseInterface
                {
                    $statusCode = $throwable instanceof ThrowingDomainException ? 422 : 500;
                    $title = $throwable instanceof ThrowingDomainException ? 'Domain Failure' : 'Internal Server Error';
                    $type = $throwable instanceof ThrowingDomainException
                        ? 'https://example.com/problems/domain-failure'
                        : 'about:blank';

                    $response = $this->responseFactory
                        ->createResponse($statusCode, $title)
                        ->withHeader('Content-Type', 'application/problem+json');

                    $response->getBody()->write((string) json_encode([
                        'type' => $type,
                        'title' => $title,
                        'status' => $statusCode,
                    ], JSON_THROW_ON_ERROR));

                    return $response;
                }
            },
            $router,
        );

        $domainResponse = $entrypoint->handle($this->getRequest('/throwing-flow/domain-exception'));
        $unknownResponse = $entrypoint->handle($this->getRequest('/invalid-handler/invalid'));

        self::assertSame(422, $domainResponse->getStatusCode());
        self::assertSame('application/problem+json', $domainResponse->getHeaderLine('Content-Type'));
        self::assertSame('true', $domainResponse->getHeaderLine('X-Global-Decorator'));
        self::assertSame(
            [
                'type' => 'https://example.com/problems/domain-failure',
                'title' => 'Domain Failure',
                'status' => 422,
            ],
            json_decode((string) $domainResponse->getBody(), true),
        );

        self::assertSame(500, $unknownResponse->getStatusCode());
        self::assertSame('application/problem+json', $unknownResponse->getHeaderLine('Content-Type'));
        self::assertSame('true', $unknownResponse->getHeaderLine('X-Global-Decorator'));
        self::assertSame(
            [
                'type' => 'about:blank',
                'title' => 'Internal Server Error',
                'status' => 500,
            ],
            json_decode((string) $unknownResponse->getBody(), true),
        );
    }

    private function getRequest(string $endpoint, string $type = 'GET'): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($type, sprintf('http://localhost/%s', ltrim($endpoint, '/')));
    }

    /**
     * @param array<class-string<PowerModule>> $modules
     */
    private function getEntrypoint(
        array $modules,
        ?HttpEntrypointMiddlewareInterface $httpEntrypointMiddleware = null,
        ?SyntheticResponseFactoryInterface $syntheticResponseFactory = null,
        ?ResponseDecoratorChainInterface $responseDecoratorChain = null,
    ): HttpEntrypointInterface {
        $responseDecoratorChain ??= new ResponseDecoratorChain();
        $syntheticResponseFactory ??= new SyntheticResponseFactory(new ResponseFactory());

        return new HttpEntrypoint(
            $httpEntrypointMiddleware ?? new ExceptionHandlingMiddleware($responseDecoratorChain, new ResponseFactory()),
            $this->getRouter($modules, $syntheticResponseFactory, $responseDecoratorChain),
        );
    }

    /**
     * @param array<class-string<PowerModule>> $modules
     */
    private function getRouter(
        array $modules,
        SyntheticResponseFactoryInterface $syntheticResponseFactory,
        ResponseDecoratorChainInterface $responseDecoratorChain,
    ): ModularRouterInterface {
        $router = new Router($syntheticResponseFactory, $responseDecoratorChain);

        foreach ($modules as $moduleName) {
            /** @var PowerModule $powerModule */
            $powerModule = new $moduleName();
            $moduleContainer = new ConfigurableContainer();
            $powerModule->register($moduleContainer);
            $router->registerPowerModuleRoutes($powerModule, $moduleContainer);
        }

        return $router;
    }
}
