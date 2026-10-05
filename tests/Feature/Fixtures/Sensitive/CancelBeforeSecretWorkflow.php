<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Workflow;
use Tests\Feature\Fixtures\SensitiveUserAction;

class CancelBeforeSecretWorkflow extends Workflow
{
    protected array $actions = [
        CancelingAction::class,
        SensitiveUserAction::class,
    ];
}
