<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Stringable;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\suggest;

/**
 * BaseCommand
 */
abstract class BaseCommand extends GeneratorCommand
{
    use CreatesMatchingTest;

    /**
     * Get a list of possible domains
     */
    public function possibleDomains(): array
    {
        $modelPath = app()->path('Brain');

        if (! File::exists($modelPath)) {
            File::makeDirectory($modelPath, 0755, true);
        }

        return collect(File::directories($modelPath))
            ->map(fn (string $file): string => basename($file))->sort()
            ->values()->all();
    }

    /**
     * Get the default namespace for the class being generated.
     *
     * @param  string  $rootNamespace  The root namespace of the application
     * @return string The default namespace for the task class
     */
    #[Override]
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        $root = config('brain.root');
        $type = str($this->type)->plural()->toString();

        if ($root) {
            $rootNamespace .= "\\{$root}";
        }

        if (config('brain.use_domains', false) === false) {
            return "$rootNamespace\\$type";
        }

        $domain = $this->hasArgument('domain') ? $this->argument('domain') : 'TempDomain';

        return "{$rootNamespace}\\$domain\\$type";
    }

    /**
     * After prompting for missing arguments we will suggest the model and domain
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->isReservedName($this->getNameInput()) || $this->didReceiveOptions($input)) {
            return;
        }

        if ($input->hasArgument('model')) {
            $model = suggest(
                label: 'What model this belongs to?',
                options: $this->possibleModels(),
                required: true
            );

            if ($model !== '' && $model !== '0') {
                $input->setArgument('model', $model);
            }
        }

        if ($input->hasArgument('domain')) {
            $domain = suggest(
                label: 'What domain this belongs to?',
                options: $this->possibleDomains(),
                required: true
            );

            if ($domain !== '' && $domain !== '0') {
                $input->setArgument('domain', $domain);
            }
        }

        if (
            $input->hasOption('pest')
            && ! $input->getOption('pest')
        ) {
            $pest = suggest(
                label: 'Do you want to generate a Pest test?',
                options: ['Yes', 'No'],
                required: true
            );

            if ($pest === 'Yes') {
                $input->setOption('pest', true);
            }
        }
    }

    /** Handle test file creation based on command options. */
    protected function handleTestCreation($path): bool
    {
        if (! $this->option('test') && ! $this->option('pest') && ! $this->option('phpunit')) {
            return false;
        }

        $name = (new Stringable($path))->after($this->laravel['path'])->beforeLast('.php')->append('Test')->replace('\\', '/');
        $stub = (new Stringable((new Stringable($path))->explode(DIRECTORY_SEPARATOR)->pop(2)->last()))->lower()->singular()->toString();

        return $this->call('brain:make:test', [
            'name' => $name,
            '--stub' => $stub,
            '--pest' => $this->option('pest'),
            '--phpunit' => $this->option('phpunit'),
        ]) === 0;
    }
}
