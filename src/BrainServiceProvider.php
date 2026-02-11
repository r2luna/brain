<?php

declare(strict_types=1);

namespace Brain;

use Brain\Console\EjectCommand;
use Brain\Console\ShowBrainCommand;
use Brain\Processes\Console\MakeProcessCommand;
use Brain\Queries\Console\MakeQueryCommand;
use Brain\Tasks\Console\MakeTaskCommand;
use Brain\Tests\Console\MakeTestCommand;
use Illuminate\Support\Facades\Event;
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
        $this->registerCommands();
        $this->registerListeners();
        $this->offerPublishing();
    }

    /**
     * Boot the service provider.
     *
     * This method is called after all other service providers have been registered.
     * It is used to register any commands or perform any bootstrapping tasks.
     */
    public function boot(): void {}

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
            EjectCommand::class,
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
            $config = __DIR__.'/../config/brain.php';

            $this->publishes([$config => base_path('config/brain.php')], ['brain:config']);

            $this->mergeConfigFrom($config, 'brain');
        }
    }

    private function registerListeners(): void
    {
        if (! config('brain.log')) {
            return;
        }

        $processEvents = [
            Processes\Events\Processing::class,
            Processes\Events\Processed::class,
            Processes\Events\Error::class,
        ];

        foreach ($processEvents as $event) {
            Event::listen($event, Processes\Listeners\LogEventListener::class);
        }

        $taskEvents = [
            Tasks\Events\Processing::class,
            Tasks\Events\Processed::class,
            Tasks\Events\Cancelled::class,
            Tasks\Events\Skipped::class,
            Tasks\Events\Error::class,
        ];

        foreach ($taskEvents as $event) {
            Event::listen($event, Tasks\Listeners\LogEventListener::class);
        }
    }
}
