<?php

declare(strict_types=1);

namespace Modular\Router\Contract;

interface HasCustomRouteSlug
{
    public function getRouteSlug(): string;
}
