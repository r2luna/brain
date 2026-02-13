<?php

declare(strict_types=1);

namespace Brain\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OnQueue
{
    public function __construct(public string $queue) {}
}
