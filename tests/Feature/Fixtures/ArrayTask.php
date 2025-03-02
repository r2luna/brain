<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Task;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * @property-read string $name
 */
class ArrayTask extends Task
{
    use Dispatchable;

    public function handle(): self
    {
        return $this;
    }
}
