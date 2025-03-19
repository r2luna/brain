<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Brain\Example\Processes;

use Brain\Process;
use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4;

class ExampleProcess extends Process
{
    protected array $tasks = [
        ExampleTask4::class,
    ];
}
