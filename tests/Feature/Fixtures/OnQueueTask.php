<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Attributes\OnQueue;
use Brain\Task;
use Illuminate\Contracts\Queue\ShouldQueue;

#[OnQueue('custom')]
class OnQueueTask extends Task implements ShouldQueue
{
    public function handle(): self
    {
        return $this;
    }
}
