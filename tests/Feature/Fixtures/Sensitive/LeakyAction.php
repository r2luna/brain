<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Action;
use Brain\Attributes\Sensitive;
use RuntimeException;

#[Sensitive('password')]
class LeakyAction extends Action
{
    public function handle(): self
    {
        throw new RuntimeException("Invalid credentials: {$this->password}");
    }
}
