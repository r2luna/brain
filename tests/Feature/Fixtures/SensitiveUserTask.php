<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Attributes\Sensitive;
use Brain\Task;

/**
 * @property-read string $email
 * @property string $password
 * @property string $credit_card
 */
#[Sensitive('password', 'credit_card')]
class SensitiveUserTask extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
