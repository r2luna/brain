<?php

declare(strict_types=1);

namespace Brain\Tasks\Middleware;

use Brain\Task;

/** Middleware that finalizes a task after the pipeline completes. */
final class FinalizeTaskMiddleware
{
    /** Run the next middleware and then finalize the task. */
    public function handle(Task $task, callable $next): void
    {
        $next($task);

        $task->finalize();
    }
}
