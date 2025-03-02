<?php

declare(strict_types=1);

use Brain\Exceptions\InvalidPayload;
use Brain\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

test('make sure that it is using the correct traits', function () {
    $expectedTraits = [
        Dispatchable::class,
        InteractsWithQueue::class,
        Queueable::class,
        SerializesModels::class,
    ];

    $actualTraits = class_uses(Task::class);

    expect($actualTraits)->toHaveKeys($expectedTraits);
});

it('should validate the payload of a Task based on the docblock of the class', function () {
    /** @property-read string $name */
    class TempTask extends Task {}

    TempTask::dispatch();
})->throws(InvalidPayload::class);

it('should make sure that we standardize the payload in an object', function () {
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

it('should delay the task if the runIn method is set', function () {});
