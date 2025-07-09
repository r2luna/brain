<?php

declare(strict_types=1);

use Brain\Exceptions\InvalidPayload;
use Brain\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

function getJobFromReflection(PendingDispatch $task): object
{
    $reflection = new ReflectionClass($task);
    $property = $reflection->getProperty('job');
    $property->setAccessible(true);

    return $property->getValue($task);
}

test('make sure that it is using the correct traits', function (): void {
    $expectedTraits = [
        Dispatchable::class,
        InteractsWithQueue::class,
        Queueable::class,
        SerializesModels::class,
    ];

    $actualTraits = class_uses(Task::class);

    expect($actualTraits)->toHaveKeys($expectedTraits);
});

it('should validate the payload of a Task based on the docblock of the class', function (): void {
    /** @property-read string $name */
    class TempTask extends Task {}

    TempTask::dispatch();
})->throws(InvalidPayload::class);

it('should return void if there is nothing to validate', function (): void {
    class ValidateVoidTask extends Task {}

    $task = ValidateVoidTask::dispatch();
})->throwsNoExceptions();

it('should make sure that we standardize the payload in an object', function (): void {
    /** @property-read string $name */
    class ArrayTask extends Task {}
    $task = ArrayTask::dispatch(['name' => 'John Doe']);
    $job = getJobFromReflection($task);

    expect($job->payload)->toBeObject();

    class NullTask extends Task {}
    $task = NullTask::dispatch();
    expect($job->payload)->toBeObject();

    /** @property-read string $name */
    class ObjectTask extends Task {}
    $task = ObjectTask::dispatch((object) ['name' => 'John Doe']);
    expect($job->payload)->toBeObject();
});

it('should delay the task if the runIn method is set', function (): void {
    class DelayTask extends Task
    {
        public function runIn(): int
        {
            return 10;
        }
    }

    $task = DelayTask::dispatch();
    $job = getJobFromReflection($task);
    expect($job->delay)->toBe(10);
});

it('s possible to return int or a Carbon instance', function (): void {
    class DelayIntTask extends Task
    {
        public function runIn(): int
        {
            return 10;
        }
    }

    $task = DelayIntTask::dispatch();

    $job = getJobFromReflection($task);

    expect($job->delay)->toBe(10);

    Carbon::setTestNow('2021-01-01 01:00:00');

    class DelayCarbonTask extends Task
    {
        public function runIn(): Carbon
        {
            return now()->addSeconds(10);
        }
    }

    $task = DelayCarbonTask::dispatch();
    $job = getJobFromReflection($task);
    expect($job->delay)->toBeInstanceOf(Carbon::class);
    expect($job->delay)->format('Y-m-d h:i:s')->toBe('2021-01-01 01:00:10');
});

test('if runIn is not set delay should be null', function (): void {
    class NoDelayTask extends Task {}

    $task = NoDelayTask::dispatch();
    $job = getJobFromReflection($task);
    expect($job->delay)->toBeNull();
});

it('should add cancelProcess to the payload when cancelProcess method is called', function (): void {
    class CancelTask extends Task
    {
        public function handle(): self
        {
            $this->cancelProcess();

            return $this;
        }
    }

    $task = CancelTask::dispatchSync();

    expect($task->payload)->toHaveKey('cancelProcess');
    expect($task->payload->cancelProcess)->toBeTrue();
});

test('from inside a task we should be able to access payload data magically using __get method', function (): void {
    /** @property-read string $name */
    class MagicTask extends Task
    {
        public function handle(): self
        {
            expect($this->name)->toBe('John Doe');

            return $this;
        }
    }

    MagicTask::dispatch(['name' => 'John Doe']);
});

it('should be able to set any property in the payload magically using __set method', function (): void {
    /** @property string $name */
    class Magic2Task extends Task
    {
        public function handle(): self
        {
            $this->name = 'John Doe';

            return $this;
        }
    }

    $task = Magic2Task::dispatchSync();

    expect($task->name)->toBe('John Doe');
});

it('should be possible to have a conditional run inside a process', function (): void {
    /**
     * Class ConditionalTask
     *
     * @property-read int $id
     */
    class ConditionalTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }

        protected function runIf(): bool
        {
            return $this->id > 2;
        }
    }

    class ConditionalProcess extends Brain\Process
    {
        protected array $tasks = [
            ConditionalTask::class,
        ];
    }

    Bus::fake([
        ConditionalTask::class,
    ]);

    ConditionalProcess::dispatch(['id' => 1]);

    Bus::assertNotDispatched(ConditionalTask::class);
});

it('should be able to conditionally run outside a process', function (): void {
    Bus::fake([Temp2Task::class]);

    class Temp2Task extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    /**
     * Class ConditionalOutTask
     *
     * @property-read int $id
     */
    class ConditionalOutTask extends Task
    {
        public function handle(): self
        {
            Temp2Task::dispatch();

            return $this;
        }

        protected function runIf(): bool
        {
            return false;
        }
    }

    ConditionalOutTask::dispatch(['id' => 1]);

    Bus::assertNotDispatched(Temp2Task::class);

});
