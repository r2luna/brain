<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Process;

class ValidatingProcess extends Process
{
    protected array $tasks = [
        ValidatedSecretTask::class,
    ];
}
