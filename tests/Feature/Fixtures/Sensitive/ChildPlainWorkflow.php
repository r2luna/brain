<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Workflow;

class ChildPlainWorkflow extends Workflow
{
    protected array $actions = [
        RecordingAction::class,
    ];
}
