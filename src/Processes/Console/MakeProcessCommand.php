<?php

declare(strict_types=1);

namespace Brain\Processes\Console;

use Brain\Console\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Class ProcessesMakeCommand
 */
#[AsCommand(name: 'make:process')]
final class MakeProcessCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:process';

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
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    #[Override]
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        $domain = $this->hasArgument('domain') ? $this->argument('domain') : 'TempDomain';

        return "$rootNamespace\Brain\\$domain\Processes";
    }
}
