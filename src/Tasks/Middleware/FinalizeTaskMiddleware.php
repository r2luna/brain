<?php

declare(strict_types=1);

namespace Brain\Tasks\Middleware;

use Brain\Task;
use Brain\Tasks\Events\Error as TasksError;
use Illuminate\Support\Facades\Context;
use Throwable;

/** Middleware that finalizes a task after the pipeline completes. */
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
            // @codeCoverageIgnoreStart
            // The coverage is ignored because the event doesn't dispatch event in the test environment
        } catch (Throwable $e) {
            $meta = [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ];

            event(new TasksError($task::class, payload: $task->payload, runProcessId: $runProcessId, meta: $meta));

            $task->fail($e);

            throw $e;
            // @codeCoverageIgnoreEnd
        }
    }
}
