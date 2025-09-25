<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

use Psr\Http\Message\ResponseInterface;

interface HasResponseDecorators
{
    /**
     * @return array<callable(ResponseInterface):ResponseInterface>
     */
    public function getResponseDecorators(): array;
}
