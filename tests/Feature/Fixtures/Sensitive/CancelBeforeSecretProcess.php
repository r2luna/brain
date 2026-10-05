<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Process;
use Tests\Feature\Fixtures\SensitiveUserTask;

class CancelBeforeSecretProcess extends Process
{
    protected array $tasks = [
        CancelingTask::class,
        SensitiveUserTask::class,
    ];
}
