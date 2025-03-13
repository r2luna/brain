<?php

declare(strict_types=1);

use Brain\Console\ShowBrainCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Terminal;

beforeEach(function () {
    $this->command = new ShowBrainCommand;

    Artisan::registerCommand($this->command);

});

test('command has correct signature and description', function () {
    expect($this->command->getName())->toBe('brain:show')
        ->and($this->command->getDescription())->toBe('Show Brain Mapping');
});

test('terminal width is retrieved correctly', function () {
    $terminal = new Terminal;
    $reflection = new ReflectionClass($this->command);
    $method = $reflection->getMethod('getTerminalWidth');
    $method->setAccessible(true);

    expect($method->invoke($this->command))->toBe($terminal->getWidth());
});

test('domains are filtered when filter option is provided', function () {
    File::shouldReceive('directories')
        ->with(app_path('Brain'))
        ->andReturn([
            app_path('Brain/Users'),
            app_path('Brain/Orders'),
            app_path('Brain/Payments'),
        ]);

    File::shouldReceive('files')
        ->andReturn([]);

    $this->artisan(ShowBrainCommand::class, ['--filter' => 'Users'])
        ->assertSuccessful();
});

test('command displays processes and tasks correctly', function () {
    // Mock file system
    File::shouldReceive('directories')
        ->with(app_path('Brain'))
        ->andReturn([app_path('Brain/Users')]);

    File::shouldReceive('files')
        ->with(app_path('Brain/Users/Processes'))
        ->andReturn([
            'Brain/Users/Processes/RegisterUserProcess.php',
        ]);

    // Mock process file content
    File::shouldReceive('get')
        ->andReturn('
            <?php
            namespace App\Brain\Users\Processes;
            class RegisterUserProcess {
                protected array $tasks = [];
                protected bool $chain = true;
            }
        ');

    $this->artisan('brain:show')
        ->expectsOutput('USERS')
        ->assertSuccessful();
});

test('getBrainMap returns collection with correct structure', function () {
    File::shouldReceive('directories')
        ->with(app_path('Brain'))
        ->andReturn([app_path('Brain/Users')]);

    File::shouldReceive('files')
        ->with(app_path('Brain/Users/Processes'))
        ->andReturn([]);

    $reflection = new ReflectionClass($this->command);
    $method = $reflection->getMethod('getBrainMap');
    $method->setAccessible(true);

    $map = $method->invoke($this->command);

    expect($map)->toBeInstanceOf(Illuminate\Support\Collection::class)
        ->and($map->first())->toHaveKeys(['domain', 'processes']);
});

test('getClassFullNameFromFile extracts class name correctly', function () {
    $reflection = new ReflectionClass($this->command);
    $method = $reflection->getMethod('getClassFullNameFromFile');
    $method->setAccessible(true);

    $fileContent = '<?php
        namespace App\Brain\Users;
        class TestClass {}
    ';

    File::shouldReceive('get')
        ->andReturn($fileContent);

    $result = $method->invoke($this->command, 'test.php');
    expect($result)->toBe('\App\Brain\Users\TestClass');
});

test('command handles empty brain directory gracefully', function () {
    File::shouldReceive('directories')
        ->with(app_path('Brain'))
        ->andReturn([]);

    $this->artisan('brain:show')
        ->assertSuccessful();
});
