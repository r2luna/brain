<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Brain\Example2\Processes;

use Brain\Process;
use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4;

class ExampleProcess2 extends Process
{
    protected bool $chain = true;

    protected array $tasks = [
        ExampleTask4::class,
    ];
}
