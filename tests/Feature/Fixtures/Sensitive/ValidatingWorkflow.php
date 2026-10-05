<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Workflow;

class ValidatingWorkflow extends Workflow
{
    protected array $actions = [
        ValidatedSecretAction::class,
    ];
}
