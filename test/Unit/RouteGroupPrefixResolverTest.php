<?php

declare(strict_types=1);

namespace Modular\Router\Test\Unit;

use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Router\RouteGroupPrefixResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteGroupPrefixResolver::class)]
class RouteGroupPrefixResolverTest extends TestCase
{
    /**
     * @return iterable<string,array{0:PowerModule,1:string}>
     */
    public static function modulesDataProvider(): iterable
    {
        yield 'Standard module name conversion' => [
            new Sample\LibraryA\LibraryAModule(),
            '/library-a',
        ];

        yield 'Custom slug without leading slash' => [
            new Sample\LibraryB\LibraryBModule(),
            '/custom-api',
        ];

        yield 'Custom slug with leading slash' => [
            new Sample\LibraryC\LibraryCModule(),
            '/already-has-slash',
        ];
    }

    #[DataProvider('modulesDataProvider')]
    public function testResolver(
        PowerModule $module,
        string $expectedPrefix,
    ): void {
        $resolver = new RouteGroupPrefixResolver();

        self::assertSame(
            $expectedPrefix,
            $resolver->getRouteGroupPrefix($module),
        );
    }
}
