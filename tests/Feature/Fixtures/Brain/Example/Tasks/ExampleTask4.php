<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Brain\Example\Tasks;

use Brain\Task;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExampleTask4 extends Task implements ShouldQueue
{
    public function handle(): self
    {
        //

        return $this;
    }
}
