<?php

declare(strict_types=1);

namespace Modular\Router\Response;

use Laminas\Diactoros\ResponseFactory;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SyntheticResponseFactory implements SyntheticResponseFactoryInterface
{
    private const DEFAULT_PROBLEM_TYPE = 'about:blank';

    protected readonly ResponseFactoryInterface $responseFactory;

    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    public function createOptionsResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        $allowHeader = implode(', ', $allowedMethods);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Allow', $allowHeader)
            ->withHeader('Access-Control-Allow-Methods', $allowHeader);
    }

    public function createMethodNotAllowedResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        return $this->withAllowHeader(
            $this->createProblemDetailsResponse(405, 'Method Not Allowed'),
            $allowedMethods,
        );
    }

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createProblemDetailsResponse(404, 'Not Found');
    }

    /**
     * @param list<string> $allowedMethods
     */
    protected function withAllowHeader(ResponseInterface $response, array $allowedMethods): ResponseInterface
    {
        return $response->withHeader('Allow', implode(', ', $allowedMethods));
    }

    protected function createProblemDetailsResponse(int $statusCode, string $title): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($statusCode, $title)
            ->withHeader('Content-Type', 'application/problem+json');

        $response->getBody()->write((string) json_encode([
            'type' => self::DEFAULT_PROBLEM_TYPE,
            'title' => $title,
            'status' => $statusCode,
        ], JSON_THROW_ON_ERROR));

        return $response;
    }
}
