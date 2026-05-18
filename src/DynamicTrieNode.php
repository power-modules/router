<?php

declare(strict_types=1);

namespace Modular\Router;

final class DynamicTrieNode
{
    /** @var array<string, DynamicTrieNode> */
    public array $staticChildren = [];

    public ?DynamicTrieNode $placeholderChild = null;

    public ?string $placeholderName = null;

    public ?RegisteredRoute $route = null;
}
