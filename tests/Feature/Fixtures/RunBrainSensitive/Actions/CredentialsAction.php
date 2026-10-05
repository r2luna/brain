<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrainSensitive\Actions;

use Brain\Action;

/**
 * @property-read string $email
 * @property-read string $password
 * @property-read string $token
 */
class CredentialsAction extends Action
{
    public function handle(): self
    {
        return $this;
    }
}
