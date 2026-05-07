<?php

declare(strict_types=1);

namespace Brain\Concerns;

use Throwable;

/**
 * Default lifecycle hooks for Brain components.
 *
 * Sync execution (`::run()`) drives hooks via runWithHooks() in this trait.
 * Queued execution drives hooks via HookLifecycleMiddleware (registered in
 * the using class's middleware() method). The two paths are exclusive — no
 * double-fire — because Laravel only runs job middleware via CallQueuedHandler
 * (the worker path), not via dispatchSync().
 */
trait HasLifecycleHooks
{
    /**
     * Override to transform the payload before the component is dispatched.
     */
    public static function before(array|object|null $payload): array|object|null
    {
        return $payload;
    }

    /**
     * Override to handle exceptions raised during the component.
     * The default re-throws; an override may return a fallback value to recover
     * (a subclass may narrow the return type via covariance).
     *
     * Note: in queued execution, the return value is ignored and the original
     * exception is re-thrown so Laravel can drive its retry behavior.
     */
    public static function onError(Throwable $e, array|object|null $payload): mixed
    {
        throw $e;
    }

    /**
     * Override to run cleanup or logging that must happen regardless of success.
     * Receives the (possibly transformed) payload and the error if one was thrown.
     */
    public static function finally(array|object|null $payload, ?Throwable $error): void
    {
        //
    }

    /**
     * Sync hook pipeline: before → dispatchSync → after, with onError on
     * exceptions and finally always running. Used by ::run() in Workflow / Action.
     * Queued execution uses HookLifecycleMiddleware instead.
     */
    protected static function runWithHooks(array|object|null $payload): mixed
    {
        $error = null;
        $result = null;

        try {
            $payload = static::before($payload);
            $result = static::dispatchSync($payload);
            $result = static::after($result);
        } catch (Throwable $e) {
            $error = $e;
            $result = static::onError($e, $payload);
        } finally {
            static::finally($payload, $error);
        }

        return $result;
    }
}
