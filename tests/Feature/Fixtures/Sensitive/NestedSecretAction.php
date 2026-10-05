<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Action;
use Brain\Attributes\Sensitive;

#[Sensitive('user.password')]
class NestedSecretAction extends Action
{
    public function handle(): self
    {
        return $this;
    }
}
