<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

use Psr\Http\Server\MiddlewareInterface;

interface HasMiddleware
{
    /**
     * @return list<class-string<MiddlewareInterface>>
     */
    public function getMiddleware(): array;
}
