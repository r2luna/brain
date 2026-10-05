<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Attributes\Sensitive;
use Brain\Task;
use RuntimeException;

#[Sensitive('password')]
class LeakyTask extends Task
{
    public function handle(): self
    {
        throw new RuntimeException("Invalid credentials: {$this->password}");
    }
}
