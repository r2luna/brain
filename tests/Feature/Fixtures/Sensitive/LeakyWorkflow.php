<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Workflow;

class LeakyWorkflow extends Workflow
{
    protected array $actions = [
        LeakyAction::class,
    ];
}
