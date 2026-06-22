<?php

declare(strict_types=1);

use Brain\Attributes\Sensitive;
use Brain\Broadcasting\Events\ProcessFinished;
use Brain\Broadcasting\Events\ProcessStarted;
use Brain\Broadcasting\Events\TaskFinished;
use Brain\Broadcasting\Events\TaskStarted;
use Brain\Process;
use Brain\Task;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

test('broadcast events work with sensitive data attributes on process', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    #[Sensitive('password', 'secret')]
    class SensitiveProcess extends Process
    {
        public $userId;

        protected array $tasks = [];

        protected function startedBroadcastMessage(): array
        {
            return [
                'message' => 'Processing sensitive data',
                'user_id' => $this->userId ?? 'unknown',
            ];
        }
    }

    $process = new SensitiveProcess([
        'userId' => 123,
        'password' => 'secret123',
        'secret' => 'confidential',
        'publicData' => 'visible',
    ]);

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, function (ProcessStarted $event): bool {
        // Should contain public payload keys but sensitive handling is at display level
        $payloadKeys = $event->meta['payload_keys'];

        return in_array('userId', $payloadKeys)
            && in_array('password', $payloadKeys)
            && in_array('secret', $payloadKeys)
            && in_array('publicData', $payloadKeys);
    });
});

test('broadcast events work with sensitive data attributes on task', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    #[Sensitive('apiKey')]
    class SensitiveTask extends Task
    {
        public function handle(): static
        {
            return $this;
        }

        protected function startedBroadcastMessage(): array
        {
            return [
                'message' => 'Processing with API',
                'endpoint' => $this->endpoint ?? 'unknown',
                // Note: We can access sensitive data in broadcast messages
                // but should be careful about what we expose
            ];
        }
    }

    $process = new class(['endpoint' => 'https://api.example.com', 'apiKey' => 'secret-key-123']) extends Process
    {
        protected array $tasks = [SensitiveTask::class];
    };

    $process->handle();

    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['endpoint'] === 'https://api.example.com');
});

test('broadcast events work with queued tasks', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    class QueuedBroadcastTask extends Task implements Illuminate\Contracts\Queue\ShouldQueue
    {
        use Illuminate\Bus\Queueable;

        public function handle(): static
        {
            return $this;
        }

        protected function startedBroadcastMessage(): array
        {
            return [
                'message' => 'Queued task started',
                'queue_name' => $this->queue ?? 'default',
            ];
        }
    }

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [QueuedBroadcastTask::class];
    };

    $process->handle();

    // Note: Queued tasks will have their constructor called (which fires TaskStarted)
    // but the handle() method (which fires TaskFinished) will run asynchronously
    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Queued task started');
});

test('broadcast events work with conditional task execution', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    class BroadcastConditionalTask extends Task
    {
        public function handle(): static
        {
            return $this;
        }

        protected function runIf(): bool
        {
            return $this->shouldRun ?? false;
        }

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'Conditional task started'];
        }

        protected function finishedBroadcastMessage(): array
        {
            return ['message' => 'Conditional task finished'];
        }
    }

    // Test with task that should NOT run
    $process1 = new class(['shouldRun' => false]) extends Process
    {
        protected array $tasks = [BroadcastConditionalTask::class];
    };

    $process1->handle();

    // Task constructor is called (TaskStarted fired) but handle() is not called (no TaskFinished)
    // This is correct behavior - the task is instantiated to check runIf()
    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Conditional task started');

    // Task should not finish since runIf() returned false
    Event::assertNotDispatched(TaskFinished::class);

    Event::fake(); // Reset events

    // Test with task that SHOULD run
    $process2 = new class(['shouldRun' => true]) extends Process
    {
        protected array $tasks = [BroadcastConditionalTask::class];
    };

    $process2->handle();

    // Task should start and finish
    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Conditional task started');

    Event::assertDispatched(TaskFinished::class, fn (TaskFinished $event): bool => $event->messageData['message'] === 'Conditional task finished');
});

test('broadcast events work with nested processes', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    class SubProcess extends Process
    {
        protected array $tasks = [];

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'Sub-process started'];
        }
    }

    class MainProcess extends Process
    {
        protected array $tasks = [SubProcess::class];

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'Main process started'];
        }
    }

    $mainProcess = new MainProcess(['value' => 0]);
    $mainProcess->handle();

    // Should see events from both main process and sub-process
    Event::assertDispatchedTimes(ProcessStarted::class, 2);
    Event::assertDispatchedTimes(ProcessFinished::class, 2);

    // Check specific messages
    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => $event->messageData['message'] === 'Main process started');

    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => $event->messageData['message'] === 'Sub-process started');
});

test('broadcast events work with task cancellation', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    class CancellingTask extends Task
    {
        public function handle(): static
        {
            $this->cancelProcess();

            return $this;
        }

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'Task that cancels process'];
        }
    }

    class AfterCancelTask extends Task
    {
        public function handle(): static
        {
            return $this;
        }

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'This should not run'];
        }
    }

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [CancellingTask::class, AfterCancelTask::class];
    };

    $process->handle();

    // Only the first task should start and finish
    Event::assertDispatchedTimes(TaskStarted::class, 1);
    Event::assertDispatchedTimes(TaskFinished::class, 1);

    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Task that cancels process');

    // The second task should not even start
    Event::assertNotDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'This should not run');
});

test('broadcast events work with task validation errors', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    /**
     * @property-read string $requiredField
     */
    class ValidatingTask extends Task
    {
        public function handle(): static
        {
            return $this;
        }

        protected function rules(): array
        {
            return [
                'requiredField' => ['required', 'string'],
            ];
        }

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'Validating task started'];
        }
    }

    $process = new class(['otherField' => 'value']) extends Process
    {
        protected array $tasks = [ValidatingTask::class];
    };

    try {
        $process->handle();
    } catch (Illuminate\Validation\ValidationException) {
        // Expected validation error
    }

    // Task constructor should not even complete due to validation error
    // So no broadcast events should be dispatched
    Event::assertNotDispatched(TaskStarted::class);
    Event::assertNotDispatched(TaskFinished::class);
});

test('broadcast events work with delayed tasks', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    class DelayedTask extends Task implements Illuminate\Contracts\Queue\ShouldQueue
    {
        use Illuminate\Bus\Queueable;

        public function handle(): static
        {
            return $this;
        }

        protected function runIn(): int
        {
            return 300; // 5 minutes delay
        }

        protected function startedBroadcastMessage(): array
        {
            return ['message' => 'Delayed task scheduled'];
        }
    }

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [DelayedTask::class];
    };

    $process->handle();

    // Task constructor is called (which fires TaskStarted) even for delayed tasks
    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Delayed task scheduled');
});

test('broadcast configuration can be changed during runtime', function (): void {
    Event::fake();

    // Start with broadcasting disabled
    Config::set('brain.broadcast.enabled', false);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertNotDispatched(ProcessStarted::class);

    // Enable broadcasting for next process
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process2 = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process2->handle();

    Event::assertDispatched(ProcessStarted::class);
});

test('broadcast events contain ISO-8601 formatted timestamps', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, function (ProcessStarted $event): bool {
        // Check if timestamp is in ISO-8601 format
        $timestamp = $event->broadcastWith()['timestamp'];
        $parsed = DateTime::createFromFormat(DateTime::ISO8601, $timestamp);

        return $parsed !== false || DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $timestamp) !== false;
    });
});
