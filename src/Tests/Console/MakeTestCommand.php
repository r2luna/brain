<?php

declare(strict_types=1);

namespace Brain\Tests\Console;

use Illuminate\Foundation\Console\TestMakeCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to generate a new test class for Brain.
 */
class MakeTestCommand extends TestMakeCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'brain:make:test';

    /** Get the console command options, including the stub type. */
    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [['stub', null, InputOption::VALUE_NONE, 'Stub type to be generated']]
        );
    }

    /** Get the default namespace for the generated test class. */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace;
    }

    /** Resolve the path to the stub file based on the selected stub option. */
    protected function resolveStubPath($stub): string
    {
        return __DIR__.'/stubs/'.$this->option('stub').'.stub';
    }
}
