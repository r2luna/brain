<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Attributes\Sensitive;
use Brain\Workflow;

#[Sensitive('password')]
class BaseSensitiveWorkflow extends Workflow
{
    protected array $actions = [
        RecordingAction::class,
    ];
}
