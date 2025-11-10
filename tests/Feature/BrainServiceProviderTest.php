<?php

declare(strict_types=1);

use Brain\BrainServiceProvider;
use Brain\Process;
use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Fixtures\SimpleTask;

beforeEach(function (): void {
    $this->provider = new BrainServiceProvider($this->app);
});

test('service provider registers all make commands', function (): void {
    $this->provider->boot();

    $registeredCommands = array_keys(Artisan::all());

    expect($registeredCommands)->toContain('make:process');
    expect($registeredCommands)->toContain('make:task');
    expect($registeredCommands)->toContain('make:query');
});

test('registered commands are of correct instance type', function (): void {
    $this->provider->boot();
    $commands = Artisan::all();

    expect($commands['make:process'])->toBeInstanceOf(MakeProcessCommand::class);
    expect($commands['make:task'])->toBeInstanceOf(MakeTaskCommand::class);
    expect($commands['make:query'])->toBeInstanceOf(MakeQueryCommand::class);
});

test('if config brain log is enabled needs to register listeners', function (): void {
    config()->set('brain.log', true);

    $this->provider->register();

    $processEvents = [
        Brain\Processes\Events\Processing::class,
        Brain\Processes\Events\Processed::class,
        Brain\Processes\Events\Error::class,
    ];

    $taskEvents = [
        Brain\Tasks\Events\Processing::class,
        Brain\Tasks\Events\Processed::class,
        Brain\Tasks\Events\Cancelled::class,
        Brain\Tasks\Events\Skipped::class,
        Brain\Tasks\Events\Error::class,
    ];

    $appListeners = app()->make('events')->getRawListeners();

    // check if the events where registered
    foreach (array_merge($processEvents, $taskEvents) as $event) {
        expect(array_keys($appListeners))
            ->toContain($event);
    }

    // check if the listeners are correct
    foreach ($processEvents as $event) {
        expect(data_get($appListeners, "{$event}.0"))
            ->toBe(Brain\Processes\Listeners\LogEventListener::class);
    }

    foreach ($taskEvents as $event) {
        expect(data_get($appListeners, "{$event}.0"))
            ->toBe(Brain\Tasks\Listeners\LogEventListener::class);
    }
});

test('if config brain log is not enabled dont register anything', function (): void {
    config()->set('brain.log', false);

    $this->provider->boot();

    $processEvents = [
        Brain\Processes\Events\Processing::class,
        Brain\Processes\Events\Processed::class,
        Brain\Processes\Events\Error::class,
    ];

    $taskEvents = [
        Brain\Tasks\Events\Processing::class,
        Brain\Tasks\Events\Processed::class,
        Brain\Tasks\Events\Cancelled::class,
        Brain\Tasks\Events\Skipped::class,
        Brain\Tasks\Events\Error::class,
    ];

    $appListeners = Event::getRawListeners();

    foreach (array_merge($processEvents, $taskEvents) as $event) {
        expect(array_keys($appListeners))
            ->not
            ->toContain($event);
    }

});

test('make sure the LogEventListener works', function (): void {
    config()->set('brain.log', false);

    $this->provider->boot();

    // Let's manually register the listener for this test
    // just this event, I don't need to check all of them here
    Event::listen(Brain\Processes\Events\Processing::class, Brain\Processes\Listeners\LogEventListener::class);
    Event::listen(Brain\Tasks\Events\Processing::class, Brain\Tasks\Listeners\LogEventListener::class);

    $process = new Process(['key' => 'value']);
    $process->addTask(SimpleTask::class);

    $uuid = $process->uuid;

    Log::shouldReceive('info')
        ->with(
            "(id: $uuid) Process Event: Brain\Processes\Events\Processing",
            Mockery::on(fn ($arg): bool => isset($arg['runId'], $arg['process'], $arg['payload'], $arg['timestamp']))
        )
        ->once();

    Log::shouldReceive('info')
        ->with(
            "(id: $uuid) Task Event: Brain\Tasks\Events\Processing",
            Mockery::on(fn ($arg): bool => isset($arg['runId'], $arg['process'], $arg['task'], $arg['payload'], $arg['timestamp']))
        )
        ->once();

    $process->handle();
});
