<?php

declare(strict_types=1);

use Brain\BrainServiceProvider;
use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->provider = new BrainServiceProvider($this->app);
});

test('service provider registers all make commands', function () {
    $this->provider->boot();

    $registeredCommands = array_keys(Artisan::all());

    expect($registeredCommands)->toContain('make:process');
    expect($registeredCommands)->toContain('make:task');
    expect($registeredCommands)->toContain('make:query');
});

test('registered commands are of correct instance type', function () {
    $this->provider->boot();
    $commands = Artisan::all();

    expect($commands['make:process'])->toBeInstanceOf(MakeProcessCommand::class);
    expect($commands['make:task'])->toBeInstanceOf(MakeTaskCommand::class);
    expect($commands['make:query'])->toBeInstanceOf(MakeQueryCommand::class);
});
