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

test('process broadcasts with empty payload', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => isset($event->meta['payload_keys']) && empty($event->meta['payload_keys']));
});

test('process broadcasts with array payload', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['key1' => 'value1', 'key2' => 'value2']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => $event->meta['payload_keys'] === ['key1', 'key2']);
});

test('task broadcasts contain process context information', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $processName = $process::class;
    $process->handle();

    Event::assertDispatched(TaskStarted::class, fn (TaskStarted $event): bool => $event->meta['process'] === $processName
        && isset($event->meta['runProcessId'])
        && is_string($event->meta['runProcessId']));

    Event::assertDispatched(TaskFinished::class, fn (TaskFinished $event): bool => $event->meta['process'] === $processName
        && isset($event->meta['runProcessId'])
        && is_string($event->meta['runProcessId']));
});

test('task generates unique IDs for same task class with different payloads', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    $process1 = new class(['value' => 0, 'id' => 1]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process2 = new class(['value' => 0, 'id' => 2]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process1->handle();
    $process2->handle();

    $taskIds = [];
    Event::assertDispatched(TaskStarted::class, function (TaskStarted $event) use (&$taskIds): bool {
        $taskIds[] = $event->id;

        return true;
    });

    expect($taskIds)->toHaveCount(2)
        ->and($taskIds[0])->not->toBe($taskIds[1]);
});

test('broadcast messages handle null and false values correctly', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];

        protected function startedBroadcastMessage(): array
        {
            return [
                'null_value' => null,
                'false_value' => false,
                'zero_value' => 0,
                'empty_string' => '',
                'empty_array' => [],
            ];
        }
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, function (ProcessStarted $event): bool {
        $message = $event->messageData;

        return array_key_exists('null_value', $message)
            && $message['null_value'] === null
            && array_key_exists('false_value', $message)
            && $message['false_value'] === false
            && $message['zero_value'] === 0
            && $message['empty_string'] === ''
            && $message['empty_array'] === [];
    });
});

test('broadcast events handle complex data structures', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.tasks', true);

    class ComplexDataTask extends Task
    {
        public function handle(): static
        {
            return $this;
        }

        protected function startedBroadcastMessage(): array
        {
            return [
                'nested_array' => [
                    'level1' => [
                        'level2' => ['deep_value' => 'test'],
                    ],
                ],
                'object_data' => (object) ['prop' => 'value'],
                'mixed_array' => [1, 'string', true, null],
            ];
        }
    }

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [ComplexDataTask::class];
    };

    $process->handle();

    Event::assertDispatched(TaskStarted::class, function (TaskStarted $event): bool {
        $message = $event->messageData;

        return $message['nested_array']['level1']['level2']['deep_value'] === 'test'
            && $message['object_data']->prop === 'value'
            && $message['mixed_array'] === [1, 'string', true, null];
    });
});

test('process with chained tasks broadcasts correctly', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['value' => 0]) extends Process
    {
        protected bool $chain = true;

        protected array $tasks = [SimpleTask::class];
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class);
    Event::assertDispatched(ProcessFinished::class);
});

test('process broadcasts during error scenarios', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    class FailingTask extends Task
    {
        public function handle(): static
        {
            throw new Exception('Task failed');
        }
    }

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [FailingTask::class];
    };

    try {
        $process->handle();
    } catch (Exception) {
        // Expected to fail
    }

    // Process should broadcast started event even if it fails later
    Event::assertDispatched(ProcessStarted::class);
    // Process should NOT broadcast finished event if it fails
    Event::assertNotDispatched(ProcessFinished::class);
});

test('task broadcasts are not affected by process broadcast settings', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', false); // Disabled
    Config::set('brain.broadcast.tasks', true); // Enabled

    $process = new class(['value' => 0]) extends Process
    {
        protected array $tasks = [SimpleTask::class];
    };

    $process->handle();

    // Process events should not be dispatched
    Event::assertNotDispatched(ProcessStarted::class);
    Event::assertNotDispatched(ProcessFinished::class);

    // Task events should still be dispatched
    Event::assertDispatched(TaskStarted::class);
    Event::assertDispatched(TaskFinished::class);
});

test('broadcast events contain correct timestamps', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $startTime = microtime(true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    $endTime = microtime(true);

    Event::assertDispatched(ProcessStarted::class, function (ProcessStarted $event) use ($startTime, $endTime): bool {
        $eventTime = $event->meta['timestamp'];

        return $eventTime >= $startTime && $eventTime <= $endTime;
    });

    Event::assertDispatched(ProcessFinished::class, function (ProcessFinished $event) use ($startTime, $endTime): bool {
        $eventTime = $event->meta['timestamp'];

        return $eventTime >= $startTime && $eventTime <= $endTime;
    });
});

test('broadcast events use UUID as process ID', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, function (ProcessStarted $event): bool {
        // UUID format: 8-4-4-4-12 characters
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        return preg_match($uuidPattern, $event->id) === 1;
    });
});

test('broadcast message methods are protected and can be overridden', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    $process = new class(['test' => 'value']) extends Process
    {
        protected array $tasks = [];

        // Test that we can override these protected methods
        protected function startedBroadcastMessage(): array
        {
            return ['overridden' => 'started'];
        }

        protected function finishedBroadcastMessage(): array
        {
            return ['overridden' => 'finished'];
        }
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, fn (ProcessStarted $event): bool => $event->messageData['overridden'] === 'started');

    Event::assertDispatched(ProcessFinished::class, fn (ProcessFinished $event): bool => $event->messageData['overridden'] === 'finished');
});

test('broadcast events respect inheritance hierarchy', function (): void {
    Event::fake();
    Config::set('brain.broadcast.enabled', true);
    Config::set('brain.broadcast.processes', true);

    abstract class BaseCustomProcess extends Process
    {
        protected function startedBroadcastMessage(): array
        {
            return ['base' => 'message', 'type' => 'base'];
        }
    }

    $process = new class(['test' => 'value']) extends BaseCustomProcess
    {
        protected array $tasks = [];

        protected function startedBroadcastMessage(): array
        {
            return array_merge(parent::startedBroadcastMessage(), [
                'type' => 'child',
                'additional' => 'data',
            ]);
        }
    };

    $process->handle();

    Event::assertDispatched(ProcessStarted::class, function (ProcessStarted $event): bool {
        $message = $event->messageData;

        return $message['base'] === 'message'
            && $message['type'] === 'child' // Overridden by child
            && $message['additional'] === 'data';
    });
});
