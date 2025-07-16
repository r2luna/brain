<?php

declare(strict_types=1);

namespace Brain\Tests\Console;

use Illuminate\Foundation\Console\TestMakeCommand;

class MakeTestCommand extends TestMakeCommand
{
    protected $name = 'brain:make:test';

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace;
    }

    protected function resolveStubPath($stub): string
    {
        return __DIR__.$stub;
    }
}
