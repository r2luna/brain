<?php

declare(strict_types=1);

namespace Brain\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Sensitive
{
    public array $keys;

    public function __construct(string ...$keys)
    {
        $this->keys = $keys;
    }
}
