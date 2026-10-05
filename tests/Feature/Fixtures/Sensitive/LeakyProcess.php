<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Process;

class LeakyProcess extends Process
{
    protected array $tasks = [
        LeakyTask::class,
    ];
}
