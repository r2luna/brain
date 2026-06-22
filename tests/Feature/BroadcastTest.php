<?php

declare(strict_types=1);

use Brain\Broadcasting\Events\ProcessFinished;
use Brain\Broadcasting\Events\ProcessStarted;
use Brain\Broadcasting\Events\TaskFinished;
use Brain\Broadcasting\Events\TaskStarted;
use Brain\Process;
use Brain\Task;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Fixtures\SimpleTask;

test('process fires broadcast events when broadcast is enabled', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];

        protected function startedBroadcastMessage(): array
        {
            return [
                'message' => 'Custom process started message',
                'custom_data' => 'test_data',
            ];
        }

        protected function finishedBroadcastMessage(): array
        {
            return [
                'message' => 'Custom process finished message',
                'result' => 'success',
            ];
        }
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => $event->name === $process::class
        && $event->messageData['message'] === 'Custom process started message'
        && $event->messageData['custom_data'] === 'test_data'
        && isset($event->meta['timestamp']));

    Event::assertDispatched(ProcessFinished::class, fn (ProcessFinished $event): bool => $event->name === $process::class
        && $event->messageData['message'] === 'Custom process finished message'
        && $event->messageData['result'] === 'success'
        && isset($event->meta['timestamp']));
});

test('process does not fire broadcast events when broadcast is disabled', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', false);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertNotDispatched(ProcessStarted::class);
    Event::assertNotDispatched(ProcessFinished::class);
});

test('process does not fire broadcast events when process broadcasting is disabled', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', false);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertNotDispatched(ProcessStarted::class);
    Event::assertNotDispatched(ProcessFinished::class);
});

test('task fires broadcast events when broadcast is enabled', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process->handle();

    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->name === SimpleTask::class
        && $event->messageData['message'] === 'Task started'
        && isset($event->meta['timestamp']));

    Event::assertDispatched(TaskFinished::class, fn (TaskFinished $event): bool => $event->name === SimpleTask::class
        && $event->messageData['message'] === 'Task completed successfully'
        && isset($event->meta['timestamp']));
});

test('task does not fire broadcast events when broadcast is disabled', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', false);

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process->handle();

    Event::assertNotDispatched(TaskStarted::class);
    Event::assertNotDispatched(TaskFinished::class);
});

test('task does not fire broadcast events when task broadcasting is disabled', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', false);

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process->handle();

    Event::assertNotDispatched(TaskStarted::class);
    Event::assertNotDispatched(TaskFinished::class);
});

test('broadcast events contain correct channel information', function (): void {
    $event = new ProcessStarted(
        'test-uuid-123',
        'TestProcess',
        ['message' => 'test'],
        ['timestamp' => 123.456]
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(3)
        ->and($channels[0]->name)->toBe('brain')
        ->and($channels[1]->name)->toBe('brain.process')
        ->and($channels[2]->name)->toBe('brain.process.test-uuid-123');
});

test('broadcast events contain correct broadcast data', function (): void {
    $event = new TaskStarted(
        'task-123',
        'TestTask',
        ['message' => 'Task started', 'data' => 'custom'],
        ['timestamp' => 123.456, 'process' => 'TestProcess']
    );

    $broadcastData = $event->broadcastWith();

    expect($broadcastData)
        ->toHaveKeys(['id', 'name', 'type', 'event', 'message', 'meta', 'timestamp'])
        ->and($broadcastData['id'])->toBe('task-123')
        ->and($broadcastData['name'])->toBe('TestTask')
        ->and($broadcastData['type'])->toBe('task')
        ->and($broadcastData['event'])->toBe('started')
        ->and($broadcastData['message'])->toBe(['message' => 'Task started', 'data' => 'custom'])
        ->and($broadcastData['meta'])->toBe(['timestamp' => 123.456, 'process' => 'TestProcess'])
        ->and($broadcastData['timestamp'])->toBeString();
});

test('broadcast events have correct broadcast event names', function (): void {
    $processStarted = new ProcessStarted('id', 'Process', [], []);
    $processFinished = new ProcessFinished('id', 'Process', [], []);
    $taskStarted = new TaskStarted('id', 'Task', [], []);
    $taskFinished = new TaskFinished('id', 'Task', [], []);

    expect($processStarted->broadcastAs())->toBe('brain.process.started')
        ->and($processFinished->broadcastAs())->toBe('brain.process.finished')
        ->and($taskStarted->broadcastAs())->toBe('brain.task.started')
        ->and($taskFinished->broadcastAs())->toBe('brain.task.finished');
});

test('process with default broadcast messages', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => $event->messageData['message'] === 'Process started'
        && $event->messageData['tasks_count'] === 0);

    Event::assertDispatched(ProcessFinished::class, fn (ProcessFinished $event): bool => $event->messageData['message'] === 'Process completed successfully'
        && $event->messageData['tasks_count'] === 0);
});

test('task with default broadcast messages', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process->handle();

    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Task started');

    Event::assertDispatched(TaskFinished::class, fn (TaskFinished $event): bool => $event->messageData['message'] === 'Task completed successfully');
});

test('task custom broadcast messages', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    class CustomBroadcastTask extends Task
    {
        public function handle(): static
        {
            return $this;
        }

        protected function startedBroadcastMessage(): array
        {
            return [
                'message' => 'Custom task started',
                'task_data' => 'custom_info',
            ];
        }

        protected function finishedBroadcastMessage(): array
        {
            return [
                'message' => 'Custom task finished',
                'result' => 'success',
            ];
        }
    }

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [CustomBroadcastTask::class];
    };

    $process->handle();

    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->messageData['message'] === 'Custom task started'
        && $event->messageData['task_data'] === 'custom_info');

    Event::assertDispatched(TaskFinished::class, fn (TaskFinished $event): bool => $event->messageData['message'] === 'Custom task finished'
        && $event->messageData['result'] === 'success');
});
