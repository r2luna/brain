<?php

declare(strict_types=1);

use Brain\Tests\Console\MakeTestCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

test('MakeTestCommand has the correct command name', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTestCommand($files);

    expect($command->getName())->toBe('brain:make:test');
});

test('MakeTestCommand extends TestMakeCommand', function (): void {
    $reflection = new ReflectionClass(MakeTestCommand::class);
    $parentClass = $reflection->getParentClass();

    expect($parentClass->getName())->toBe(Illuminate\Foundation\Console\TestMakeCommand::class);
});

test('getDefaultNamespace returns the root namespace', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTestCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $result = $method->invoke($command, 'App');

    expect($result)->toBe('App');
});

test('resolveStubPath resolves path relative to command directory', function (): void {

    // Create a mock input object
    $input = Mockery::mock(Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getOption')->with('stub')->andReturn('task');

    $files = app(Filesystem::class);
    $command = new MakeTestCommand($files);

    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getProperty('input');
    $inputProperty->setValue($command, $input);

    $method = $reflection->getMethod('resolveStubPath');

    $result = $method->invoke($command, '/stubs/task.stub');

    // The result should be the __DIR__ of the MakeTestCommand class plus the stub path
    $expectedPath = dirname((new ReflectionClass(MakeTestCommand::class))->getFileName()).'/stubs/task.stub';
    expect($result)->toBe($expectedPath);
});

test('MakeTestCommand can be registered and executed', function (): void {
    // Create a custom implementation of MakeTestCommand for testing
    $files = app(Filesystem::class);
    $command = new class($files) extends MakeTestCommand
    {
        // Override the handle method to avoid actually creating a file
        public function handle()
        {
            // Just return success without doing anything
            return 0;
        }

        // Override the resolveStubPath method to return a path that exists
        protected function resolveStubPath($stub): string
        {
            return __DIR__.'/../../Fixtures/empty.stub';
        }
    };

    // Register the command
    Artisan::registerCommand($command);

    // Execute the command and assert it exits with code 0
    $this->artisan('brain:make:test', ['name' => 'ExampleTest'])
        ->assertExitCode(0);
});
