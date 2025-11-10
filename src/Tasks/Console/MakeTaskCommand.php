<?php

declare(strict_types=1);

namespace Brain\Tasks\Console;

use Brain\Console\BaseCommand;
use Override;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class TaskMakeCommand
 *
 * This command is designed to generate a new task class.
 */
final class MakeTaskCommand extends BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'brain:make:task';

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected $aliases = ['make:task'];

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
     * Get the name input for the class.
     *
     * @return string The name of the class
     */
    #[Override]
    protected function getNameInput(): string
    {
        $name = trim($this->argument('name'));

        if (config('brain.use_suffix', false) === false) {
            return $name;
        }

        $suffix = config('brain.suffixes.task');

        return str_ends_with($name, (string) $suffix) ? $name : "{$name}{$suffix}";
    }

    /**
     * Get the console command arguments required for this command.
     *
     * @return array<int, array<string, int, string>> An array of arguments with their details
     */
    #[Override]
    protected function getArguments(): array
    {
        $arguments = [
            ['name', InputArgument::REQUIRED, 'The name of the task. Ex.: RegisterNewPto.'],
        ];

        if (config('brain.use_domains', false) === true) {
            $arguments[] = ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'];
        }

        return $arguments;
    }
}
