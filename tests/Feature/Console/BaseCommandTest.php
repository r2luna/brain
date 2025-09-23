<?php

declare(strict_types=1);

use Brain\Console\BaseCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputArgument;

test('BaseCommand is abstract', function (): void {
    $reflection = new ReflectionClass(BaseCommand::class);
    expect($reflection->isAbstract())->toBeTrue();
});

test('BaseCommand has possibleDomains method', function (): void {
    $reflection = new ReflectionClass(BaseCommand::class);
    $method = $reflection->getMethod('possibleDomains');
    expect($method)->not->toBeNull();
});

test('handleTestCreation should return false if no test options is informed', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        public function handle(): void
        {
            $this->input->setOption('pest', true);
        }

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }
    };

    // Create a mock input object
    $input = Mockery::mock(Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getOption')->with('test')->andReturn(false);
    $input->shouldReceive('getOption')->with('pest')->andReturn(false);
    $input->shouldReceive('getOption')->with('phpunit')->andReturn(false);

    // Set the input property on the command
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getProperty('input');
    $inputProperty->setValue($command, $input);

    // Now invoke the handleTestCreation method
    $reflection = new ReflectionClass(BaseCommand::class);
    $method = $reflection->getMethod('handleTestCreation');
    $result = $method->invoke($command, 'pest');

    expect($result)->toBeFalse();
});

test('handleTestCreation should return true when brain:make:test command succeeds', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        // Mock the call method to return 0 (success)
        public function call($command, array $arguments = [])
        {
            // Verify the command and arguments are correct
            expect($command)->toBe('brain:make:test');
            expect($arguments)->toHaveKey('name');
            expect($arguments)->toHaveKey('--pest');
            expect($arguments)->toHaveKey('--phpunit');

            return 0; // Return success
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }
    };

    // Create a mock input object with test option enabled
    $input = Mockery::mock(Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getOption')->with('test')->andReturn(true);
    $input->shouldReceive('getOption')->with('pest')->andReturn(false);
    $input->shouldReceive('getOption')->with('phpunit')->andReturn(false);

    // Set the input property on the command
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getProperty('input');
    $inputProperty->setValue($command, $input);

    // Set up the laravel property for path resolution
    $laravelProperty = $reflection->getProperty('laravel');
    $laravelProperty->setValue($command, ['path' => app_path()]);

    // Now invoke the handleTestCreation method
    $reflection = new ReflectionClass(BaseCommand::class);
    $method = $reflection->getMethod('handleTestCreation');
    $result = $method->invoke($command, app_path('TestPath.php'));

    expect($result)->toBeTrue();
});

test('possibleDomains method returns array', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }
    };

    File::shouldReceive('exists')
        ->andReturn(true);

    File::shouldReceive('directories')
        ->andReturn(['Brain/Tasks', 'Brain/Queries', 'Brain/Processes']);

    $domains = $command->possibleDomains();

    expect($domains)->toBeArray();
    expect($domains)->toBe(['Processes', 'Queries', 'Tasks']);
});

it('should create brain directory if it doesnt exists', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }
    };

    $modelPath = app()->path('Brain');

    if (File::exists($modelPath)) {
        File::deleteDirectory($modelPath);
    }

    $command->possibleDomains();

    expect(File::exists($modelPath))->toBeTrue();
});

test('check if we open a suggestion box', function (): void {

    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        protected $type = 'Test';

        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }

        #[Override]
        protected function getArguments(): array
        {
            return [
                ['name', InputArgument::REQUIRED, 'The name of the query'],
                ['model', InputArgument::OPTIONAL, 'The name of the model'],
                ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
            ];
        }
    };

    Artisan::registerCommand($command);

    $this->artisan('test:command')
        ->expectsQuestion('What should the test be named?', 'Example')
        ->expectsQuestion('What model this belongs to?', 'User')
        ->expectsQuestion('What domain this belongs to?', 'Tasks')
        ->expectsQuestion('Do you want to generate a Pest test?', 'Yes')
        ->assertExitCode(0);
});

test('selection with different values for model domain and pest', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        protected $type = 'Test';

        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }

        #[Override]
        protected function getArguments(): array
        {
            return [
                ['name', InputArgument::REQUIRED, 'The name of the query'],
                ['model', InputArgument::OPTIONAL, 'The name of the model'],
                ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
            ];
        }
    };

    Artisan::registerCommand($command);

    // Test with different model
    $this->artisan('test:command')
        ->expectsQuestion('What should the test be named?', 'Example')
        ->expectsQuestion('What model this belongs to?', 'Post')
        ->expectsQuestion('What domain this belongs to?', 'Tasks')
        ->expectsQuestion('Do you want to generate a Pest test?', 'Yes')
        ->assertExitCode(0);

    // Test with different domain
    $this->artisan('test:command')
        ->expectsQuestion('What should the test be named?', 'Example')
        ->expectsQuestion('What model this belongs to?', 'User')
        ->expectsQuestion('What domain this belongs to?', 'Queries')
        ->expectsQuestion('Do you want to generate a Pest test?', 'Yes')
        ->assertExitCode(0);

    // Test with No for Pest test
    $this->artisan('test:command')
        ->expectsQuestion('What should the test be named?', 'Example')
        ->expectsQuestion('What model this belongs to?', 'User')
        ->expectsQuestion('What domain this belongs to?', 'Tasks')
        ->expectsQuestion('Do you want to generate a Pest test?', 'No')
        ->assertExitCode(0);
});

test('selection with empty values', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        protected $type = 'Test';

        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }

        #[Override]
        protected function getArguments(): array
        {
            return [
                ['name', InputArgument::REQUIRED, 'The name of the query'],
                ['model', InputArgument::OPTIONAL, 'The name of the model'],
                ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
            ];
        }
    };

    Artisan::registerCommand($command);

    // The suggest function requires a value, so we'll use a value that will be treated as empty
    // in the afterPromptingForMissingArguments method
    $this->artisan('test:command')
        ->expectsQuestion('What should the test be named?', 'Example')
        ->expectsQuestion('What model this belongs to?', '0')
        ->expectsQuestion('What domain this belongs to?', '0')
        ->expectsQuestion('Do you want to generate a Pest test?', 'No')
        ->assertExitCode(0);
});

test('selection with options already provided', function (): void {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        protected $type = 'Test';

        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }

        #[Override]
        protected function getArguments(): array
        {
            return [
                ['name', InputArgument::REQUIRED, 'The name of the query'],
                ['model', InputArgument::OPTIONAL, 'The name of the model'],
                ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
            ];
        }
    };

    Artisan::registerCommand($command);

    // Test with options already provided
    $this->artisan('test:command Example --pest')
        ->assertExitCode(0);
});

it('should cancel the creation if the name of the element is a reserved name', function (): void {

    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        protected $type = 'Test';

        public function handle(): void {}

        public function getStub()
        {
            return '';
        }

        protected function configure(): void
        {
            $this->setName('test:command');
        }

        #[Override]
        protected function getArguments(): array
        {
            return [
                ['name', InputArgument::REQUIRED, 'The name of the query'],
                ['model', InputArgument::OPTIONAL, 'The name of the model'],
                ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
            ];
        }
    };

    Artisan::registerCommand($command);

    foreach ([
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'enum',
        'eval',
        'exit',
        'extends',
        'false',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'namespace',
        'new',
        'or',
        'parent',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'self',
        'static',
        'switch',
        'throw',
        'trait',
        'true',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
        '__CLASS__',
        '__DIR__',
        '__FILE__',
        '__FUNCTION__',
        '__LINE__',
        '__METHOD__',
        '__NAMESPACE__',
        '__TRAIT__',
    ] as $reservedName) {
        $this->artisan('test:command')
            ->expectsQuestion('What should the test be named?', $reservedName)
            ->assertExitCode(0);
    }
});
