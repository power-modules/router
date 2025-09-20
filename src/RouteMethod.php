<?php

declare(strict_types=1);

namespace Modular\Router;

enum RouteMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Delete = 'DELETE';
    case Patch = 'PATCH';
    case Options = 'OPTIONS';
    case Head = 'HEAD';
}
