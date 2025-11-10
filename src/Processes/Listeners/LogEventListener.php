<?php

declare(strict_types=1);

namespace Brain\Processes\Listeners;

use Brain\Processes\Events\BaseEvent;
use Illuminate\Support\Facades\Log;

class LogEventListener
{
    /**
     * Handle the event.
     */
    public function handle(BaseEvent $event): void
    {
        $class = $event::class;

        Log::info(
            "(id: {$event->runProcessId}) Process Event: {$class}",
            [
                'runId' => $event->runProcessId,
                'process' => $event->process,
                'payload' => $event->payload,
                'timestamp' => now()->toDateTimeString(),
                'meta' => $event->meta,
            ]
        );
    }
}
