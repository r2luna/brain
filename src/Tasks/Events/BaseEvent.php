<?php

declare(strict_types=1);

namespace Brain\Tasks\Events;

use Illuminate\Foundation\Events\Dispatchable;

class BaseEvent
{
    use Dispatchable;

    public function __construct(
        public string $task,
        public array|object|null $payload,
        public ?string $process = null,
        public ?string $runProcessId = null,
        public array $meta = []
    ) {}
}
