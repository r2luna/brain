<?php

declare(strict_types=1);

namespace Brain\Queries\Console;

use Brain\Console\BaseCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Override;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class QueriesMakeCommand
 *
 * This command is designed to generate a new query class.
 */
class MakeQueryCommand extends BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'brain:make:query';

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected $aliases = ['make:query'];

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new query class';

    /**
     * The type of class to generate.
     *
     * @var string
     */
    protected $type = 'Query';

    /**
     * Get the path to the stub file for the generator.
     *
     * @return string The file path of the stub
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/query.stub';
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

        $suffix = config('brain.suffixes.query');

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
            ['name', InputArgument::REQUIRED, 'The name of the query'],
            ['model', InputArgument::OPTIONAL, 'The name of the model'],
        ];

        if (config('brain.use_domains', false) === true) {
            $arguments[] = ['domain', InputArgument::OPTIONAL, 'The domain of the query'];
        }

        return $arguments;
    }

    /**
     * Build the class with the given name, replacing placeholders in the stub.
     *
     * @param  string  $name  The name of the class to generate
     * @return string The modified class content
     *
     * @throws FileNotFoundException
     */
    #[Override]
    protected function buildClass($name): string // @pest-ignore-type
    {
        $class = parent::buildClass($name);

        return str_replace(['{{ model }}', '{{model}}'], $this->argument('model'), $class);
    }
}
