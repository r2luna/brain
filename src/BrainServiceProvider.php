<?php

declare(strict_types=1);

namespace Brain;

use Brain\Console\ShowBrainCommand;
use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Brain\Tests\Console\MakeTestCommand;
use Illuminate\Support\ServiceProvider;

/**
 * BrainServiceProvider is responsible for bootstrapping and registering
 * console commands for the Brain package. It extends the base ServiceProvider
 * class provided by Laravel.
 *
 * Methods:
 * - register(): Merges the package's configuration file with the application's configuration, ensuring that the configuration values defined in 'config/brain.php' are available under the 'brain' namespace.
 * - boot(): Called after all other service providers have been registered. It is used to register any commands or perform any bootstrapping tasks.
 * - registerCommands(): Registers the console commands for the application, binding the specified command classes to the application's command registry.
 */
class BrainServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method merges the package's configuration file with the application's
     * configuration. It ensures that the configuration values defined in
     * 'config/brain.php' are available under the 'brain' namespace.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/brain.php',
            'brain'
        );
    }

    /**
     * Boot the service provider.
     *
     * This method is called after all other service providers have been registered.
     * It is used to register any commands or perform any bootstrapping tasks.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->offerPublishing();
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
            MakeTestCommand::class,
        ]);
    }

    /**
     * Offer the publishing of configuration files for the package.
     *
     * This method registers the publishing of the package's configuration file
     * to the application's configuration directory, allowing users to customize
     * the package's behavior.
     */
    private function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/brain.php' => config_path('brain.php'),
            ]);
        }
    }
}
