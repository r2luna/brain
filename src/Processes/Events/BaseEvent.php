<?php

declare(strict_types=1);

namespace Brain\Processes\Events;

use Illuminate\Foundation\Events\Dispatchable;

class BaseEvent
{
    use Dispatchable;

    public function __construct(
        public string $process,
        public string $runProcessId,
        public array|object $payload,
        public array $meta = []
    ) {}
}
