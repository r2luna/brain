<?php

declare(strict_types=1);

namespace Tests;

use Brain\BrainServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [BrainServiceProvider::class];
    }
}
