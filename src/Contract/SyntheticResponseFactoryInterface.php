<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface SyntheticResponseFactoryInterface
{
    /**
     * @param list<string> $allowedMethods
     */
    public function createOptionsResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface;

    /**
     * @param list<string> $allowedMethods
     */
    public function createMethodNotAllowedResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface;

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface;
}
