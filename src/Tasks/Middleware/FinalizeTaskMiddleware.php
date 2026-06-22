<?php

declare(strict_types=1);

namespace Brain\Tasks\Middleware;

use Brain\Task;
use Brain\Tasks\Events\Error as TasksError;
use Illuminate\Support\Facades\Context;
use Throwable;

/**
 * Middleware that finalizes a task after the pipeline completes.
 *
 * @deprecated Use Brain\Actions\Middleware\FinalizeActionMiddleware instead.
 */
final class FinalizeTaskMiddleware
{
    /**
     * Run the next middleware and then finalize the task.
     *
     * @throws Throwable
     */
    public function handle(Task $task, callable $next): void
    {
        [, $runProcessId] = Context::get('process');

        try {
            $next($task);

            $task->finalize();
        } catch (Throwable $e) {
            $meta = [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ];

            event(new TasksError($task::class, payload: $task->payload, runProcessId: $runProcessId, meta: $meta));

            // Re-throw without calling $task->fail(): marking the job as failed here makes
            // Laravel's Worker skip its retry path, silently breaking $tries/backoff() on
            // queued tasks. Letting the exception bubble up lets the Worker release the job
            // for retry (or fail it terminally once attempts are exhausted).
            throw $e;
        }
    }
}
