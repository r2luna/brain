<?php

declare(strict_types=1);

namespace Brain;

use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Illuminate\Support\ServiceProvider;

class BrainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCommands();
        $this->offerPublishing();
    }

    private function registerCommands(): void
    {
        $this->commands([
            MakeProcessCommand::class,
            MakeTaskCommand::class,
            MakeQueryCommand::class,
        ]);
    }

    private function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // ----------------------------------------------------
        // TODO: Publish the entire Architecture folder
        // ----------------------------------------------------
        // There is no config file for now
        // However, we can publish the entire Archtecture
        // folder inside the project and let the user modify it
        // according to their needs
        // ----------------------------------------------------
    }
}
