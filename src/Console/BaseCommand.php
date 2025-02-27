<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

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
        $modelPath = app_path('Brain');

        return collect(Finder::create()->directories()->depth(0)->in($modelPath))
            ->map(fn (SplFileInfo $file) => $file->getFilename())
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
