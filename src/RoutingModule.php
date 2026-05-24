<?php

declare(strict_types=1);

namespace Modular\Router;

use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\Contract\HttpEntrypointMiddlewareInterface;
use Modular\Router\Contract\ResponseDecoratorChainInterface;
use Modular\Router\Contract\SyntheticResponseFactoryInterface;
use Modular\Router\Response\ResponseDecoratorChain;
use Modular\Router\Response\SyntheticResponseFactory;
use Override;

class RoutingModule implements PowerModule, ExportsComponents
{
    #[Override]
    public static function exports(): array
    {
        return [
            SyntheticResponseFactoryInterface::class,
            ResponseDecoratorChainInterface::class,
            HttpEntrypointMiddlewareInterface::class,
        ];
    }

    #[Override]
    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(
            SyntheticResponseFactoryInterface::class,
            SyntheticResponseFactory::class,
        );

        $container->set(
            ResponseDecoratorChainInterface::class,
            ResponseDecoratorChain::class,
        );

        $container->set(
            HttpEntrypointMiddlewareInterface::class,
            ExceptionHandlingMiddleware::class,
        )->addArguments([
            ResponseDecoratorChainInterface::class,
        ]);
    }
}
