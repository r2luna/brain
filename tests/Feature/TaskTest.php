<?php

declare(strict_types=1);

use Brain\Exceptions\InvalidPayload;
use Brain\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

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

it('should make sure that we standardize the payload in an object', function (): void {
    /** @property-read string $name */
    class ArrayTask extends Task {}
    $task = ArrayTask::dispatch(['name' => 'John Doe']);
    expect($task->getJob()->payload)->toBeObject();

    class NullTask extends Task {}
    $task = NullTask::dispatch();
    expect($task->getJob()->payload)->toBeObject();

    /** @property-read string $name */
    class ObjectTask extends Task {}
    $task = ObjectTask::dispatch((object) ['name' => 'John Doe']);
    expect($task->getJob()->payload)->toBeObject();
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
    expect($task->getJob()->delay)->toBe(10);
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
    expect($task->getJob()->delay)->toBe(10);

    Carbon::setTestNow('2021-01-01 01:00:00');

    class DelayCarbonTask extends Task
    {
        public function runIn(): Carbon
        {
            return now()->addSeconds(10);
        }
    }

    $task = DelayCarbonTask::dispatch();
    expect($task->getJob()->delay)->toBeInstanceOf(Carbon::class);
    expect($task->getJob()->delay)->format('Y-m-d h:i:s')->toBe('2021-01-01 01:00:10');
});

test('if runIn is not set delay should be null', function (): void {
    class NoDelayTask extends Task {}

    $task = NoDelayTask::dispatch();
    expect($task->getJob()->delay)->toBeNull();
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
