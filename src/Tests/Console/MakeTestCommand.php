<?php

declare(strict_types=1);

namespace Brain\Tests\Console;

use Illuminate\Foundation\Console\TestMakeCommand;

/**
 * Command to generate a new test class for Brain.
 */
class MakeTestCommand extends TestMakeCommand
{
    /**
     * The name and signature of the command.
     *
     * Laravel's TestMakeCommand defines a $signature, which takes precedence
     * over $name and getOptions(), so the full signature is declared again here.
     *
     * @var string
     */
    protected $signature = 'brain:make:test
                    {name : The name of the test}
                    {--f|force : Create the test even if the test already exists}
                    {--u|unit : Create a unit test}
                    {--pest : Create a Pest test}
                    {--phpunit : Create a PHPUnit test}
                    {--stub= : Stub type to be generated}';

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
