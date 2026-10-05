<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Attributes\Sensitive;
use Brain\Process;

#[Sensitive('password')]
class ParentSensitiveProcess extends Process
{
    protected array $tasks = [
        ChildPlainProcess::class,
    ];
}
