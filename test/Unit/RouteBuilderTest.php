<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit;

use Modular\Router\RouteBuilder;
use Modular\Router\RouteMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

enum TestEnum: string
{
    case User = 'user';
    case Profile = 'profile';
}

#[CoversClass(RouteBuilder::class)]
final class RouteBuilderTest extends TestCase
{
    public function testForCreatesInstanceWithDefaults(): void
    {
        $builder = RouteBuilder::for('MyController');
        $route = $builder->build();

        self::assertSame('/', $route->path);
        self::assertSame('MyController', $route->controllerName);
        self::assertSame('__invoke', $route->controllerMethodName);
        self::assertSame(RouteMethod::Get, $route->method);
        self::assertEmpty($route->getMiddleware());
    }

    public function testForWithMethodName(): void
    {
        $builder = RouteBuilder::for('MyController', 'customMethod');
        $route = $builder->build();

        self::assertSame('customMethod', $route->controllerMethodName);
    }

    public function testWithMethod(): void
    {
        $builder = RouteBuilder::for('MyController')
            ->withMethod(RouteMethod::Post);
        $route = $builder->build();

        self::assertSame(RouteMethod::Post, $route->method);
    }

    public function testWithMiddleware(): void
    {
        $middleware1 = get_class($this->createMock(MiddlewareInterface::class));
        $middleware2 = get_class($this->createMock(MiddlewareInterface::class));

        $builder = RouteBuilder::for('MyController')
            ->withMiddleware($middleware1, $middleware2);
        $route = $builder->build();

        self::assertSame([$middleware1, $middleware2], $route->getMiddleware());
    }

    public function testAddPathWithStrings(): void
    {
        $builder = RouteBuilder::for('MyController')
            ->addPath('api', 'v1', 'users');
        $route = $builder->build();

        self::assertSame('/api/v1/users', $route->path);
    }

    public function testAddPathWithEnum(): void
    {
        $builder = RouteBuilder::for('MyController')
            ->addPath('api', TestEnum::User);
        $route = $builder->build();

        self::assertSame('/api/{user}', $route->path);
    }

    public function testAddPathWithArrayOfEnums(): void
    {
        $builder = RouteBuilder::for('MyController')
            ->addPath('api', [TestEnum::User, TestEnum::Profile]);
        $route = $builder->build();

        self::assertSame('/api/{user}/{profile}', $route->path);
    }

    public function testBuildThrowsExceptionIfControllerNotSet(): void
    {
        $builder = new RouteBuilder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller name must be set using for() method');

        $builder->build();
    }
}
