<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Task;
use Illuminate\Foundation\Bus\Dispatchable;

class SimpleTask extends Task
{
    use Dispatchable;

    public function handle(): self
    {
        $this->payload->value = ($this->payload->value ?? 0) + 1;

        return $this;
    }
}
