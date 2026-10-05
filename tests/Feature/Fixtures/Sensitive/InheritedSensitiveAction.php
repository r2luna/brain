<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

/**
 * @property-read string $email
 * @property-read string $password
 */
class InheritedSensitiveAction extends BaseSensitiveAction
{
    public function handle(): self
    {
        return $this;
    }
}
