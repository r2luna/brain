<?php

declare(strict_types=1);

namespace Brain\Arch\Tasks\Console;

use Brain\Arch\Console\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class TaskMakeCommand
 *
 * This command is designed to generate a new task class.
 */
#[AsCommand(name: 'make:task')]
final class TaskMakeCommand extends BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'make:task';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new task class';

    /**
     * The type of class to generate.
     *
     * @var string
     */
    protected $type = 'Task';

    /**
     * Get the path to the stub file for the generator.
     *
     * @return string The file path of the stub
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/task.stub';
    }

    /**
     * Get the default namespace for the class being generated.
     *
     * @param  string  $rootNamespace  The root namespace of the application
     * @return string The default namespace for the task class
     */
    #[Override]
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        $domain = $this->argument('domain');

        return "$rootNamespace\Brain\\$domain\Tasks";
    }

    /**
     * Get the console command arguments required for this command.
     *
     * @return array<int, array<string, int, string>> An array of arguments with their details
     */
    #[Override]
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the task. Ex.: RegisterNewPto.'],
            ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
        ];
    }
}
