<?php

declare(strict_types=1);

namespace Brain\Concerns\Middleware;

use Brain\Action;
use Brain\Workflow;
use Throwable;

/**
 * Job middleware that runs Brain lifecycle hooks (before / after / onError /
 * finally) for queued execution. The sync path (::run()) drives hooks via
 * runWithHooks() in the HasLifecycleHooks trait, so this middleware is only
 * invoked when Laravel processes the job through CallQueuedHandler.
 *
 * Behavior in queued context:
 * - onError is invoked for instrumentation but its return value is ignored
 * - the original exception is always re-thrown so Laravel handles retries
 *
 * Skipped entirely for chained Workflows ($chain = true) — each action in
 * the chain is its own job and runs hooks via this middleware on its own.
 */
class HookLifecycleMiddleware
{
    public function handle(object $job, callable $next): mixed
    {
        if ($job instanceof Workflow && $job->isChained()) {
            return $next($job);
        }

        $error = null;
        $result = null;
        $payload = $job->payload ?? null;

        try {
            $payload = $job::before($payload);
            $job->payload = $payload;

            $next($job);

            $result = $job instanceof Action ? $job : $job->payload;
            $result = $job::after($result);
        } catch (Throwable $e) {
            $error = $e;
            try {
                $job::onError($e, $payload);
            } catch (Throwable) {
                // swallow: in queued mode the original exception always wins
            }
        } finally {
            $job::finally($payload, $error);
        }

        if ($error instanceof Throwable) {
            throw $error;
        }

        return $result;
    }
}
