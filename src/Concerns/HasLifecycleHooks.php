<?php

declare(strict_types=1);

namespace Brain\Concerns;

use Throwable;

/**
 * Shared lifecycle hooks for Brain components that flow through ::run().
 *
 * The using class must implement Dispatchable (so ::dispatchSync() exists)
 * and define after()/onError() with the appropriate return type.
 */
trait HasLifecycleHooks
{
    /**
     * Override to transform the payload before the component is dispatched.
     */
    protected static function before(array|object|null $payload): array|object|null
    {
        return $payload;
    }

    /**
     * Override to handle exceptions raised during the component.
     * The default re-throws; an override may return a fallback value to recover
     * (a subclass may narrow the return type via covariance).
     */
    protected static function onError(Throwable $e, array|object|null $payload): mixed
    {
        throw $e;
    }

    /**
     * Override to run cleanup or logging that must happen regardless of success.
     * Receives the (possibly transformed) payload and the error if one was thrown.
     */
    protected static function finally(array|object|null $payload, ?Throwable $error): void
    {
        //
    }

    /**
     * Drives the hook pipeline: before → dispatchSync → after, with onError on
     * exceptions and finally always running. Return type is loose; the public
     * ::run() in the using class narrows it.
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
