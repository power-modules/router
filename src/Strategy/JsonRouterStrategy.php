<?php

declare(strict_types=1);

namespace Modular\Router\Strategy;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class JsonRouterStrategy extends RouterStrategy
{
    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
        private readonly int $jsonFlags = 0,
    ) {
        parent::__construct($responseFactory);

        $this->addResponseDecorator(static function (ResponseInterface $response): ResponseInterface {
            if ($response->hasHeader('Content-Type')) {
                return $response;
            }

            return $response->withHeader('Content-Type', 'application/json');
        });
    }

    public function createMethodNotAllowedResponse(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        return $this->withAllowHeader(
            $this->createJsonResponse([
                'status_code' => 405,
                'reason_phrase' => 'Method Not Allowed',
            ], 405, 'Method Not Allowed'),
            $allowedMethods,
        );
    }

    public function createNotFoundResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createJsonResponse([
            'status_code' => 404,
            'reason_phrase' => 'Not Found',
        ], 404, 'Not Found');
    }

    /**
     * @param list<string> $allowedMethods
     */
    private function withAllowHeader(ResponseInterface $response, array $allowedMethods): ResponseInterface
    {
        return $response->withHeader('Allow', implode(', ', $allowedMethods));
    }

    private function createJsonResponse(mixed $payload, int $statusCode, string $reasonPhrase = ''): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($statusCode, $reasonPhrase);
        $response->getBody()->write((string) json_encode($payload, $this->jsonFlags | JSON_THROW_ON_ERROR));

        return $this->decorateResponse($response);
    }
}
