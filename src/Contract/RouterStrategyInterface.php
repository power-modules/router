<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface RouterStrategyInterface
{
    /**
     * @param callable(ResponseInterface):ResponseInterface $decorator
     */
    public function addResponseDecorator(callable $decorator): static;

    public function decorateResponse(ResponseInterface $response): ResponseInterface;

    /**
     * @param list<string> $allowedMethods
     */
    public function createOptionsResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface;

    /**
     * @param list<string> $allowedMethods
     */
    public function createMethodNotAllowedResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface;

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface;

    public function createThrowableResponse(ServerRequestInterface $request, Throwable $throwable): ?ResponseInterface;
}
