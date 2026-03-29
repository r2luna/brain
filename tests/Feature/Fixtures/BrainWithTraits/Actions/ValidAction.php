<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\BrainWithTraits\Actions;

use Brain\Action;

/**
 * @property-read string $name
 */
class ValidAction extends Action
{
    public function handle(): self
    {
        return $this;
    }
}
