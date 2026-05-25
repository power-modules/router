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
    public function __construct(
        protected readonly ResponseFactoryInterface $responseFactory = new ResponseFactory(),
        protected readonly ProblemDetailsPayloadFactory $problemDetailsPayloadFactory = new ProblemDetailsPayloadFactory(),
    ) {
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
            $this->createProblemDetailsResponse(
                405,
                'Method Not Allowed',
                $this->createMethodNotAllowedPayload($request, $allowedMethods),
            ),
            $allowedMethods,
        );
    }

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createProblemDetailsResponse(
            404,
            'Not Found',
            $this->createNotFoundPayload($request),
        );
    }

    /**
     * @param list<string> $allowedMethods
     */
    protected function withAllowHeader(ResponseInterface $response, array $allowedMethods): ResponseInterface
    {
        return $response->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * @return array<string, int|string>
     */
    protected function createNotFoundPayload(ServerRequestInterface $request): array
    {
        return $this->problemDetailsPayloadFactory->createPayload(404, 'Not Found');
    }

    /**
     * @param list<string> $allowedMethods
     * @return array<string, int|string>
     */
    protected function createMethodNotAllowedPayload(ServerRequestInterface $request, array $allowedMethods): array
    {
        return $this->problemDetailsPayloadFactory->createPayload(405, 'Method Not Allowed');
    }

    /**
     * @param array<string, int|string> $payload
     */
    protected function createProblemDetailsResponse(int $statusCode, string $title, array $payload): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($statusCode, $title)
            ->withHeader('Content-Type', 'application/problem+json');

        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response;
    }
}
