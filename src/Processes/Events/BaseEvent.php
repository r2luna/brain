<?php

declare(strict_types=1);

namespace Brain\Processes\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Base event for all process-related events. */
class BaseEvent
{
    use Dispatchable;

    /** Create a new process event instance. */
    public function __construct(
        /** The process identifier. */
        public string $process,
        /** The run process identifier. */
        public string $runProcessId,
        /** The event payload data. */
        public array|object $payload,
        /** Additional metadata for the event. */
        public array $meta = []
    ) {}
}
