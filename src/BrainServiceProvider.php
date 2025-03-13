<?php

declare(strict_types=1);

namespace Brain;

use Brain\Console\ShowBrainCommand;
use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Illuminate\Support\ServiceProvider;

/**
 * BrainServiceProvider is responsible for bootstrapping and registering
 * console commands for the Brain package. It extends the base ServiceProvider
 * class provided by Laravel.
 *
 * Methods:
 * - boot(): Called after all other service providers have been registered. It is used to register any commands or perform any bootstrapping tasks.
 * - registerCommands(): Registers the console commands for the application, binding the specified command classes to the application's command registry.
 *
 * TODO:
 * - offerPublishing(): Method to publish the entire Architecture folder inside the project for user modification. Currently commented out and not implemented.
 */
class BrainServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * This method is called after all other service providers have been registered.
     * It is used to register any commands or perform any bootstrapping tasks.
     */
    public function boot(): void
    {
        $this->registerCommands();
    }

    /**
     * Registers the console commands for the application.
     *
     * This method binds the specified command classes to the application's
     * command registry, making them available for execution via the
     * command line interface.
     **/
    private function registerCommands(): void
    {
        $this->commands([
            MakeProcessCommand::class,
            MakeTaskCommand::class,
            MakeQueryCommand::class,
            ShowBrainCommand::class,
        ]);
    }

    /**
     * ----------------------------------------------------
     * TODO: Publish the entire Architecture folder
     * ----------------------------------------------------
     * There is no config file for now
     * However, we can publish the entire Archtecture
     * folder inside the project and let the user modify it
     * according to their needs
     * ----------------------------------------------------
     */
    // private function offerPublishing(): void
    // {
    //     if (! $this->app->runningInConsole()) {
    //         return;
    //     }
    // }
}
