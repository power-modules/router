<?php

declare(strict_types=1);

namespace Modular\Router;

use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ExceptionHandlingMiddleware implements HttpEntrypointMiddlewareInterface
{
    private const DEFAULT_PROBLEM_TYPE = 'about:blank';

    private readonly ResponseFactoryInterface $responseFactory;

    public function __construct(
        private readonly ResponseDecoratorChainInterface $responseDecoratorChain,
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            return $this->responseDecoratorChain->decorateResponse(
                $this->createThrowableResponse(),
            );
        }
    }

    private function createThrowableResponse(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse(500, 'Internal Server Error')
            ->withHeader('Content-Type', 'application/problem+json');

        $response->getBody()->write((string) json_encode([
            'type' => self::DEFAULT_PROBLEM_TYPE,
            'title' => 'Internal Server Error',
            'status' => 500,
        ], JSON_THROW_ON_ERROR));

        return $response;
    }
}
