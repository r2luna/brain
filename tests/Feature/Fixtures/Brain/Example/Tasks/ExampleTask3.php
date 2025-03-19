<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Brain\Example\Tasks;

use Brain\Task;

/**
 * Task ExampleTask3
 *
 * @property-write mixed $name
 */
class ExampleTask3 extends Task
{
    public function handle(): self
    {
        //

        return $this;
    }
}
