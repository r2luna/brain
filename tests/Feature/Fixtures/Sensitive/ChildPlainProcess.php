<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Process;

class ChildPlainProcess extends Process
{
    protected array $tasks = [
        RecordingTask::class,
    ];
}
