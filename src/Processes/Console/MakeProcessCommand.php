<?php

declare(strict_types=1);

namespace Brain\Processes\Console;

use Brain\Console\BaseCommand;
use Override;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class ProcessesMakeCommand
 */
final class MakeProcessCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'brain:make:process';

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected $aliases = ['make:process'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new process class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Process';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/process.stub';
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

        $suffix = config('brain.suffixes.process');

        return str_ends_with($name, (string) $suffix) ? $name : "{$name}{$suffix}";
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    #[Override]
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        $domain = $this->hasArgument('domain') ? $this->argument('domain') : 'TempDomain';

        $rootNamespace = str($rootNamespace)->replace('\\', '')->toString();

        return "{$rootNamespace}\Brain\\$domain\Processes";
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
            ['name', InputArgument::REQUIRED, 'The name of the query'],
            ['domain', InputArgument::OPTIONAL, 'The name of the domain. Ex.: PTO'],
        ];
    }
}
