<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Attributes\Sensitive;
use Brain\Process;

#[Sensitive('password', 'credit_card')]
class SensitiveProcess extends Process
{
    protected array $tasks = [
        PlainTask::class,
    ];
}
