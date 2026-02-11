<?php

declare(strict_types=1);

namespace Brain\Tasks\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Base event for all task-related events. */
class BaseEvent
{
    use Dispatchable;

    /** Create a new task event instance. */
    public function __construct(
        /** The task identifier. */
        public string $task,
        /** The event payload data. */
        public array|object|null $payload,
        /** The parent process identifier. */
        public ?string $process = null,
        /** The run process identifier. */
        public ?string $runProcessId = null,
        /** Additional metadata for the event. */
        public array $meta = []
    ) {}
}
