<?php

declare(strict_types=1);

namespace Modular\Router\Response;

use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseDecoratorChain implements ResponseDecoratorChainInterface
{
    /**
     * @var list<callable(ResponseInterface):ResponseInterface>
     */
    private array $responseDecorators = [];

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
}
