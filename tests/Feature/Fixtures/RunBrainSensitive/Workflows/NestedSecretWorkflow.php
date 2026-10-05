<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrainSensitive\Workflows;

use Brain\Workflow;
use Tests\Feature\Fixtures\RunBrainSensitive\Actions\SecretPasswordAction;

class NestedSecretWorkflow extends Workflow
{
    protected array $actions = [
        SecretPasswordAction::class,
    ];
}
