<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\BrainWithTraits\Actions;

use Brain\Task;

/**
 * @property-read string $name
 */
class ValidAction extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
