<?php

declare(strict_types=1);

namespace Modular\Router;

use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Response\ProblemDetailsPayloadFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ExceptionHandlingMiddleware implements HttpEntrypointMiddlewareInterface
{
    public function __construct(
        private readonly ResponseDecoratorChainInterface $responseDecoratorChain,
        private readonly ResponseFactoryInterface $responseFactory = new ResponseFactory(),
        private readonly ProblemDetailsPayloadFactory $problemDetailsPayloadFactory = new ProblemDetailsPayloadFactory(),
    ) {
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
        $payload = $this->problemDetailsPayloadFactory->createPayload(500, 'Internal Server Error');
        $response = $this->responseFactory
            ->createResponse(500, 'Internal Server Error')
            ->withHeader('Content-Type', 'application/problem+json');

        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response;
    }
}
