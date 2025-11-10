<?php

declare(strict_types=1);

namespace Brain\Tasks\Listeners;

use Brain\Tasks\Events\BaseEvent;
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
            "(id: {$event->runProcessId}) Task Event: {$class}",
            [
                'runId' => $event->runProcessId,
                'task' => $event->task,
                'payload' => $event->payload,
                'process' => $event->process,
                'timestamp' => now()->toDateTimeString(),
                'meta' => $event->meta,
            ]
        );
    }
}
