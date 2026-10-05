<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrainSensitive\Workflows;

use Brain\Attributes\Sensitive;
use Brain\Workflow;
use Tests\Feature\Fixtures\RunBrainSensitive\Actions\CredentialsAction;

#[Sensitive('token')]
class LoginWorkflow extends Workflow
{
    protected array $actions = [
        CredentialsAction::class,
        NestedSecretWorkflow::class,
    ];
}
