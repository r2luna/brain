<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrain\Tasks;

use Brain\Task;
use RuntimeException;

class FailingTask extends Task
{
    public function handle(): self
    {
        throw new RuntimeException('Something went wrong');
    }
}
