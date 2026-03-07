# Events & Logging

Brain dispatches events throughout the lifecycle of processes and tasks. These can be used for logging, monitoring, or triggering additional actions.

## Enabling Logging

```bash
BRAIN_LOG_ENABLED=true
```

Or in `config/brain.php`:

```php
'log' => env('BRAIN_LOG_ENABLED', true),
```

## Process Events

| Event | When |
|-------|------|
| `Brain\Processes\Events\Processing` | Process starts executing |
| `Brain\Processes\Events\Processed` | Process completes successfully |
| `Brain\Processes\Events\Error` | Process encounters an error |

Each event contains:

- `process` — The process class name
- `runProcessId` — A unique ID for this execution
- `payload` — The data passed to the process
- `meta` — Additional metadata

## Task Events

| Event | When |
|-------|------|
| `Brain\Tasks\Events\Processing` | Task starts executing |
| `Brain\Tasks\Events\Processed` | Task completes successfully |
| `Brain\Tasks\Events\Cancelled` | Task is cancelled via `cancelProcess()` |
| `Brain\Tasks\Events\Skipped` | Task is skipped (`runIf()` returns false) |
| `Brain\Tasks\Events\Error` | Task encounters an error |

Each event contains:

- `task` — The task class name
- `payload` — The data passed to the task
- `process` — The parent process class name (if applicable)
- `runProcessId` — The process execution ID (if applicable)
- `meta` — Additional metadata

## Custom Listeners

Register listeners in your `EventServiceProvider`:

```php
use Brain\Processes\Events\Processed as ProcessCompleted;
use Brain\Tasks\Events\Error as TaskFailed;

protected $listen = [
    ProcessCompleted::class => [
        NotifyAdmin::class,
    ],
    TaskFailed::class => [
        AlertOpsTeam::class,
    ],
];
```

Or use closures:

```php
use Brain\Tasks\Events\Processed;

Event::listen(Processed::class, function ($event) {
    logger()->info('Task completed', [
        'task'    => $event->task,
        'process' => $event->process,
    ]);
});
```
