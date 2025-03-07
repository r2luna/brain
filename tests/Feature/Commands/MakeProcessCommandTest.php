
<?php

use Brain\Console\BaseCommand;
use Brain\Processes\Console\MakeProcessCommand;
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
    $command = new MakeProcessCommand($files);

    expect($command)->toBeInstanceOf(BaseCommand::class);
});

test('name should be make:process', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    expect($command->getName())->toBe('brain:make:process');
});

it('should have aliases for command signature', function () {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    expect($command->getAliases())->toBe(['make:process']);
});

test('description should be \'Create a new process class\'', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    expect($command->getDescription())->toBe('Create a new process class');
});

test('stub should be __DIR__./stubs/process/stub', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getStub');
    $method->setAccessible(true);
    $stubPath = $method->invoke($command);

    $expectedPath = realpath(__DIR__.'/../../../src/Processes/Console/stubs/process.stub');
    $actualPath = realpath($stubPath);

    expect($actualPath)->toBe($expectedPath);
});

test('get defaultNamespace', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');
    $method->setAccessible(true);

    $input = new TestInput(['domain' => 'Domain']);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    expect($defaultNamespace)->toBe('App\Brain\Domain\Processes');
});

test('get defaultNamespace with no domain', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');
    $method->setAccessible(true);

    $input = new TestInput([]);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    expect($defaultNamespace)->toBe('App\Brain\TempDomain\Processes');
});
