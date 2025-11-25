<?php

declare(strict_types=1);

namespace Brain\Tasks\Middleware;

use Brain\Task;

final class FinalizeTaskMiddleware
{
    public function handle(Task $task, callable $next): void
    {
        $task->finalize();

        $next($task);
    }
}
