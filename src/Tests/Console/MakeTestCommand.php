<?php

declare(strict_types=1);

namespace Brain\Tests\Console;

use Illuminate\Foundation\Console\TestMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeTestCommand extends TestMakeCommand
{
    protected $name = 'brain:make:test';

    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [['stub', null, InputOption::VALUE_NONE, 'Stub type to be generated']]
        );
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace;
    }

    protected function resolveStubPath($stub): string
    {
        return __DIR__.'/stubs/'.$this->option('stub').'.stub';
    }
}
