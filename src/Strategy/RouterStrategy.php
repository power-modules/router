<?php

declare(strict_types=1);

namespace Modular\Router\Strategy;

use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Contract\RouterStrategyInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class RouterStrategy implements RouterStrategyInterface
{
    /**
     * @var list<callable(ResponseInterface):ResponseInterface>
     */
    private array $responseDecorators = [];

    protected readonly ResponseFactoryInterface $responseFactory;

    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    /**
     * @param callable(ResponseInterface):ResponseInterface $decorator
     */
    public function addResponseDecorator(callable $decorator): static
    {
        $this->responseDecorators[] = $decorator;

        return $this;
    }

    public function decorateResponse(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->responseDecorators as $responseDecorator) {
            $response = $responseDecorator($response);
        }

        return $response;
    }

    public function createOptionsResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        $allowHeader = implode(', ', $allowedMethods);

        return $this->decorateResponse(
            $this->responseFactory
                ->createResponse(200)
                ->withHeader('Allow', $allowHeader)
                ->withHeader('Access-Control-Allow-Methods', $allowHeader),
        );
    }

    public function createMethodNotAllowedResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        $allowHeader = implode(', ', $allowedMethods);

        return $this->decorateResponse(
            $this->responseFactory
                ->createResponse(405, 'Method Not Allowed')
                ->withHeader('Allow', $allowHeader),
        );
    }

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->decorateResponse(
            $this->responseFactory->createResponse(404, 'Not Found'),
        );
    }

    public function createThrowableResponse(ServerRequestInterface $request, Throwable $throwable): ?ResponseInterface
    {
        return null;
    }
}
