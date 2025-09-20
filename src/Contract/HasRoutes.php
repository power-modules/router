<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

use Modular\Router\Route;

interface HasRoutes
{
    /**
     * @return array<Route>
     */
    public function getRoutes(): array;
}
