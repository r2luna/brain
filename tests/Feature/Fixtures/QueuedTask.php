<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Task;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedTask extends Task implements ShouldQueue
{
    public function handle(): self
    {
        $this->payload->queued = true;

        return $this;
    }
}
