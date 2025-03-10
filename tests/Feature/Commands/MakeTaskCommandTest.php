
<?php

use Brain\Console\BaseCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Illuminate\Filesystem\Filesystem;
use Tests\Feature\Fixtures\TestInput;

test('extends BaseCommand', function (): void {
    // ----------------------------------------------------------
    // by extending BaseCommand, MakeProcessCommand will have
    // access to the possibleDomains method and enhiert all the
    // power of the GeneratorCommand class. And since that is
    // maintained by Laravel, we can trust that it will work
    // as expected.
    // ----------------------------------------------------------
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    expect($command)->toBeInstanceOf(BaseCommand::class);
});

test('name should be make:task', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    expect($command->getName())->toBe('brain:make:task');
});

it('should have aliases for command signature', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    expect($command->getAliases())->toBe(['make:task']);
});

test('description should be \'Create a new task class\'', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    expect($command->getDescription())->toBe('Create a new task class');
});

test('stub should be __DIR__./stubs/task/stub', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getStub');
    $method->setAccessible(true);
    $stubPath = $method->invoke($command);

    $expectedPath = realpath(__DIR__.'/../../../src/Tasks/Console/stubs/task.stub');
    $actualPath = realpath($stubPath);

    expect($actualPath)->toBe($expectedPath);
});

test('get defaultNamespace', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');
    $method->setAccessible(true);

    $input = new TestInput(['domain' => 'Domain']);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    expect($defaultNamespace)->toBe('App\Brain\\Domain\\Tasks');
});

test('get defaultNamespace with no domain', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeTaskCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');
    $method->setAccessible(true);

    $input = new TestInput([]);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    expect($defaultNamespace)->toBe('App\Brain\TempDomain\Tasks');
});
