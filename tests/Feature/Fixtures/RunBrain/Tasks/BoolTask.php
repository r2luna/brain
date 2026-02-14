<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\RunBrain\Tasks;

use Brain\Task;

/**
 * @property-read bool $active
 */
class BoolTask extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
