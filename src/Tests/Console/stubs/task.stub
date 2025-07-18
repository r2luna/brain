<?php

test('check task', function (): void {
    $task = App\Brain\Domain\ExampleTask::dispatchSync();

    expect($task)->example
        ->toBeTrue();
});
