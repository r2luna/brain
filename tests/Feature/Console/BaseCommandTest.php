<?php

declare(strict_types=1);

use Brain\Console\BaseCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputArgument;

test('BaseCommand is abstract', function () {
    $reflection = new ReflectionClass(BaseCommand::class);
    expect($reflection->isAbstract())->toBeTrue();
});

test('BaseCommand has possibleDomains method', function () {
    $reflection = new ReflectionClass(BaseCommand::class);
    $method = $reflection->getMethod('possibleDomains');
    expect($method)->not->toBeNull();
});

test('possibleDomains method returns array', function () {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        public function handle() {}

        public function getStub()
        {
            return '';
        }

        protected function configure()
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

it('should create brain directory if it doesnt exists', function () {
    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        public function handle() {}

        public function getStub()
        {
            return '';
        }

        protected function configure()
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

test('check if we open a suggestion box', function () {

    $files = app(Filesystem::class);
    $command = new class($files) extends BaseCommand
    {
        protected $type = 'Test';

        public function handle() {}

        public function getStub()
        {
            return '';
        }

        protected function configure()
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
