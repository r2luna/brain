<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\suggest;

/**
 * BaseCommand
 */
abstract class BaseCommand extends GeneratorCommand
{
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
            ->map(fn (string $file): string => basename($file))
            ->sort()
            ->values()
            ->all();
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
    }
}
