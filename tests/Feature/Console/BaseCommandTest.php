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
        ->assertExitCode(0);
});

it('should cancel the criation if the name of the element is a reserved name', function (): void {

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
