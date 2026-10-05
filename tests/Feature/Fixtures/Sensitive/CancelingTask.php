<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Task;

class CancelingTask extends Task
{
    public function handle(): self
    {
        $this->cancelProcess();

        return $this;
    }
}
