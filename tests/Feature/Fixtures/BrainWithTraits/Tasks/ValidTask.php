<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\BrainWithTraits\Tasks;

use Brain\Task;

class ValidTask extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
