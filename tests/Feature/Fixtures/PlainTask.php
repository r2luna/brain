<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Task;

/**
 * @property string $password
 * @property string $email
 */
class PlainTask extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
