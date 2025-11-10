
<?php

use Brain\Console\BaseCommand;
use Brain\Processes\Console\MakeProcessCommand;
use Illuminate\Filesystem\Filesystem;
use Tests\Feature\Fixtures\TestInput;

beforeEach(function (): void {
    config()->set('brain.use_domains', true);
});

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

it('should have aliases for command signature', function (): void {
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

    $input = new TestInput(['domain' => 'Domain']);
    $command->setInput($input);

    $defaultNamespace = $method->invoke($command, 'App\\');

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();
    expect($defaultNamespace)->toBe('App\Brain\Domain\Processes');
});

test('get defaultNamespace with no domain', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $input = new TestInput([]);
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

    expect($defaultNamespace)->toBe('App\Brain\TempDomain\Processes');
});

test('getNameInput should return the name as is when suffix is disabled', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);
    $reflection = new ReflectionClass($command);

    config(['brain.use_suffix' => false]);
    $input = new TestInput(['name' => 'CreateUser']);
    $command->setInput($input);

    $method = $reflection->getMethod('getNameInput');

    $nameInput = $method->invoke($command);

    expect($nameInput)->toBe('CreateUser');
});

test('getNameInput should append Process when suffix is enabled', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);
    $reflection = new ReflectionClass($command);

    config(['brain.use_suffix' => true]);
    $input = new TestInput(['name' => 'CreateUser']);
    $command->setInput($input);

    $method = $reflection->getMethod('getNameInput');

    $nameInput = $method->invoke($command);

    expect($nameInput)->toBe('CreateUserProcess');
});

test('getNameInput should not duplicate the Process suffix', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);
    $reflection = new ReflectionClass($command);

    config(['brain.use_suffix' => true]);
    $input = new TestInput(['name' => 'CreateUserProcess']);
    $command->setInput($input);

    $method = $reflection->getMethod('getNameInput');

    $nameInput = $method->invoke($command);
    expect($nameInput)->toBe('CreateUserProcess');
});

// ------------------------------------------------------------------------------------------------------
// Disabling Domains

test('get defaultNamespace without domains', function (): void {
    config(['brain.use_domains' => false]);

    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $input = new TestInput;
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

    expect($defaultNamespace)->toBe('App\Brain\Processes');
});

// ------------------------------------------------------------------------------------------------------
// Flat Structure

test('get defaultNamespace with flat structure', function (): void {
    config(['brain.root' => null]);
    config(['brain.use_domains' => false]);

    $files = app(Filesystem::class);
    $command = new MakeProcessCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $input = new TestInput;
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

    expect($defaultNamespace)->toBe('App\Processes');
});
