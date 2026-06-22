<?php

declare(strict_types=1);

namespace Brain\Actions\Middleware;

use Brain\Action;
use Brain\Actions\Events\Error as ActionsError;
use Illuminate\Support\Facades\Context;
use Throwable;

/** Middleware that finalizes an action after the pipeline completes. */
final class FinalizeActionMiddleware
{
    /**
     * Run the next middleware and then finalize the action.
     *
     * @throws Throwable
     */
    public function handle(Action $action, callable $next): void
    {
        [, $runWorkflowId] = Context::get('workflow');

        try {
            $next($action);

            $action->finalize();
        } catch (Throwable $e) {
            $meta = [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ];

            event(new ActionsError($action::class, payload: $action->payload, runWorkflowId: $runWorkflowId, meta: $meta));

            // Re-throw without calling $action->fail(): marking the job as failed here makes
            // Laravel's Worker skip its retry path, silently breaking $tries/backoff() on
            // queued actions. Letting the exception bubble up lets the Worker release the job
            // for retry (or fail it terminally once attempts are exhausted).
            throw $e;
        }
    }
}
