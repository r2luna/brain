
<?php

use Brain\Console\BaseCommand;
use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
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
    $command = new MakeQueryCommand($files);

    expect($command)->toBeInstanceOf(BaseCommand::class);
});

test('name should be make:query', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    expect($command->getName())->toBe('brain:make:query');
});

it('should have aliases for command signature', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    expect($command->getAliases())->toBe(['make:query']);
});

test('description should be \'Create a new query class\'', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    expect($command->getDescription())->toBe('Create a new query class');
});

test('stub should be __DIR__./stubs/query/stub', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getStub');
    $method->setAccessible(true);
    $stubPath = $method->invoke($command);

    $expectedPath = realpath(__DIR__.'/../../../src/Queries/Console/stubs/query.stub');
    $actualPath = realpath($stubPath);

    expect($actualPath)->toBe($expectedPath);
});

test('get defaultNamespace', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');
    $method->setAccessible(true);

    $input = new TestInput(['domain' => 'Domain']);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    expect($defaultNamespace)->toBe('App\Brain\\Domain\\Queries');
});

test('get defaultNamespace with no domain', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');
    $method->setAccessible(true);

    $input = new TestInput([]);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    expect($defaultNamespace)->toBe('App\Brain\TempDomain\Queries');
});

it('should replace DumyModel in the stub with the given argument model', function (): void {
    $files = app(Filesystem::class);
    $input = new TestInput(['model' => 'Jeremias']);
    $command = new MakeQueryCommand($files);
    $command->setInput($input);
    $command->setLaravel(app());

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('buildClass');
    $method->setAccessible(true);

    $output = $method->invoke($command, 'UserQuery');
    expect($output)->toBe(
        file_get_contents(__DIR__.'/../Fixtures/user-query.stub')
    );
});
