<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DispatchContract;

final class RouteMiddleware extends AppendMiddlewareOrderMiddleware
{
    public function __construct()
    {
        parent::__construct('route');
    }
}
