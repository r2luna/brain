<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrainSensitive\Actions;

use Brain\Action;
use Brain\Attributes\Sensitive;

/**
 * @property-read string $password
 */
#[Sensitive('password')]
class SecretPasswordAction extends Action
{
    public function handle(): self
    {
        return $this;
    }
}
