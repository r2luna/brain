<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrain\Tasks;

use Brain\Attributes\Sensitive;
use Brain\Task;

/**
 * @property-read string $username
 * @property-read string $token
 */
#[Sensitive('token')]
class SecretTask extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
