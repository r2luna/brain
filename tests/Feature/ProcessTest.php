<?php

declare(strict_types=1);

use Brain\Process;
use Brain\Processes\Events\Processed;
use Brain\Processes\Events\Processing;
use Brain\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Fixtures\QueuedTask;
use Tests\Feature\Fixtures\SimpleTask;

test('process can be instantiated with payload', function (): void {
    $payload = ['test' => 'value'];
    $process = new Process($payload);

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->getTasks())->toBeArray()
        ->and($process->getTasks())->toBeEmpty();
});

test('process fires events when handled', function (): void {
    Event::fake();
    $process = new Process(['test' => 'value']);

    $process->handle();

    Event::assertDispatched(Processing::class);
    Event::assertDispatched(Processed::class);
});

test('process can run tasks synchronously', function (): void {
    $payload = (object) ['value' => 0];
    $process = new Process($payload);
    $process->addTask(SimpleTask::class);

    $result = $process->handle();

    expect($result->value)->toBe(1);
});

test('process can chain tasks', function (): void {
    Bus::fake();

    $payload = (object) ['test' => 'value'];
    $process = new Process($payload);
    $process->addTask(SimpleTask::class);
    $process->addTask(SimpleTask::class);

    $reflection = new ReflectionClass($process);
    $chainProperty = $reflection->getProperty('chain');
    $chainProperty->setAccessible(true);
    $chainProperty->setValue($process, true);

    $process->handle();

    Bus::assertChained([
        SimpleTask::class,
        SimpleTask::class,
    ]);
});

test('queued tasks are dispatched separately', function (): void {
    Bus::fake();

    $payload = (object) ['test' => 'value'];
    $process = new Process($payload);
    $process->addTask(QueuedTask::class);

    $process->handle();

    Bus::assertDispatched(QueuedTask::class);
});

test('process can be cancelled during execution', function (): void {
    $payload = (object) [
        'value' => 0,
        'cancelProcess' => true,
    ];

    $process = new Process($payload);
    $process->addTask(SimpleTask::class);
    $process->addTask(SimpleTask::class);

    $result = $process->handle();

    expect($result->value)->toBe(0);
});

test('process maintains task order', function (): void {
    $payload = (object) ['value' => 0];
    $process = new Process($payload);

    $process->addTask(SimpleTask::class);

    expect($process->getTasks())->toHaveCount(1)
        ->and($process->getTasks()[0])->toBe(SimpleTask::class);
});

test('process can handle null payload', function (): void {
    $process = new Process(null);

    expect(fn (): object|array|null => $process->handle())->not->toThrow(Exception::class);
});

test('process converts array payload to object', function (): void {
    $process = new Process(['key' => 'value']);
    $process->addTask(SimpleTask::class);

    $result = $process->handle();

    expect($result)->toBeObject();
});

it('should be possible to set the process as chain', function () {
    class ChainProcess extends Process
    {
        protected bool $chain = true;
    }

    $process = new ChainProcess(['test' => 'value']);
    $process->addTask(SimpleTask::class);

    expect($process->isChained())->toBeTrue();
});

test('when process is set as chain we need to dispatch a chained bus', function () {
    class QueueableTask extends Task implements ShouldQueue {}
    class Queueable2Task extends Task implements ShouldQueue {}
    class Chain2Process extends Process
    {
        protected bool $chain = true;

        protected array $tasks = [
            QueueableTask::class,
            Queueable2Task::class,
        ];
    }

    Bus::fake([
        QueueableTask::class,
        Queueable2Task::class,
    ]);

    Chain2Process::dispatchSync([]);

    Bus::assertChained([
        QueueableTask::class,
        Queueable2Task::class,
    ]);

});

it('should unser cancelProcess from the payload if the next _task_ is a process', function () {
    // ---
    // Meaning that if we have a sub process that is cancelled
    // the main process should continue to run the next task
    // ---

    class SubProcessCancelTask extends Task
    {
        public function handle(): self
        {
            $this->cancelProcess();

            return $this;
        }
    }

    class CancelProcess extends Process
    {
        protected array $tasks = [
            CancelSubProcess::class,
            SimpleTask::class,
        ];
    }

    class CancelSubProcess extends Process
    {
        protected array $tasks = [
            SubProcessCancelTask::class,
        ];
    }

    Bus::fake([SimpleTask::class]);

    CancelProcess::dispatch([]);

    Bus::assertDispatched(SimpleTask::class, function (SimpleTask $task) {
        expect($task->payload)->not->toHaveKey('cancelProcess');

        return true;
    });
});

test('if we get an exception in a process we should rollback any change occoured in the database', function () {

    class ExceptionTask extends Task
    {
        public function handle(): self
        {
            throw new Exception('Task failed');
        }
    }

    class ExceptionProcess extends Process
    {
        protected array $tasks = [
            ExceptionTask::class,
        ];
    }

    DB::shouldReceive('beginTransaction')->once();
    DB::shouldReceive('rollBack')->once();

    try {
        ExceptionProcess::dispatchSync([]);
    } catch (Throwable $th) {
        // throw $th;
    }

});
});
