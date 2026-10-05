<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrainSensitive\Actions;

use Brain\Action;
use Brain\Attributes\Sensitive;
use RuntimeException;

/**
 * @property-read string $password
 */
#[Sensitive('password')]
class LeakyLoginAction extends Action
{
    public function handle(): self
    {
        throw new RuntimeException("Invalid credentials: {$this->password}");
    }
}
