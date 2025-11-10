
<?php

use Brain\Console\BaseCommand;
use Brain\Queries\Console\MakeQueryCommand;
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

    $input = new TestInput(['domain' => 'Domain']);
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

    expect($defaultNamespace)->toBe('App\Brain\\Domain\\Queries');
});

test('get defaultNamespace with no domain', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $input = new TestInput([]);
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

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

    $output = $method->invoke($command, 'UserQuery');

    expect($output)->toBe(
        file_get_contents(__DIR__.'/../Fixtures/user-query.stub')
    );
});

test('getNameInput should return the name as is when suffix is disabled', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);
    $reflection = new ReflectionClass($command);

    config(['brain.use_suffix' => false]);
    $input = new TestInput(['name' => 'UserReport']);
    $command->setInput($input);

    $method = $reflection->getMethod('getNameInput');

    $nameInput = $method->invoke($command);

    expect($nameInput)->toBe('UserReport');
});

test('getNameInput should append Query when suffix is enabled', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);
    $reflection = new ReflectionClass($command);

    config(['brain.use_suffix' => true]);
    $input = new TestInput(['name' => 'UserReport']);
    $command->setInput($input);

    $method = $reflection->getMethod('getNameInput');

    $nameInput = $method->invoke($command);

    expect($nameInput)->toBe('UserReportQuery');
});

test('getNameInput should not duplicate the Query suffix', function (): void {
    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);
    $reflection = new ReflectionClass($command);

    config(['brain.use_suffix' => true]);
    $input = new TestInput(['name' => 'UserReportQuery']);
    $command->setInput($input);

    $method = $reflection->getMethod('getNameInput');

    $nameInput = $method->invoke($command);
    expect($nameInput)->toBe('UserReportQuery');
});

// ------------------------------------------------------------------------------------------------------
// Disabling Domains

test('get defaultNamespace without domains', function (): void {
    config(['brain.use_domains' => false]);

    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $input = new TestInput;
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

    expect($defaultNamespace)->toBe('App\Brain\Queries');
});

// ------------------------------------------------------------------------------------------------------
// Flat Structure

test('get defaultNamespace with flat structure', function (): void {
    config(['brain.root' => null]);
    config(['brain.use_domains' => false]);

    $files = app(Filesystem::class);
    $command = new MakeQueryCommand($files);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getDefaultNamespace');

    $input = new TestInput;
    $command->setInput($input);

    $defaultNamespace = str($method->invoke($command, 'App\\'))->replace('\\\\', '\\')->toString();

    expect($defaultNamespace)->toBe('App\Queries');
});
