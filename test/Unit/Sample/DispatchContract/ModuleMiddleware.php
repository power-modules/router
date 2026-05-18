<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit\Sample\DispatchContract;

final class ModuleMiddleware extends AppendMiddlewareOrderMiddleware
{
    public function __construct()
    {
        parent::__construct('module');
    }
}
