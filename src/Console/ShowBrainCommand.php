<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Console\Terminal;

class ShowBrainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brain:show {--filter=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Brain Mapping';

    /**
     * The lines to display.
     */
    private array $lines = [];

    /**
     * The terminal width.
     */
    private ?int $terminalWidth = null;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->getTerminalWidth();
        $map = $this->getBrainMap();
        $this->createLines($map);
        $this->displayLines();
    }

    /**
     * Display the output lines.
     */
    private function displayLines(): void
    {
        $this->output->writeln(
            collect($this->lines)->flatten()
        );
    }

    /**
     * Create the lines to display, based on the given map.
     */
    private function createLines(Collection $map): void
    {
        foreach ($map as $domain) {
            $currentDomain = $domain['domain'];
            $longestDomain = $this->getLengthOfTheLongestDomain($map);

            foreach ($domain['processes'] as $process) {
                $spaces = str_repeat(' ', max($longestDomain + 4 - mb_strlen((string) $currentDomain), 0));

                $this->addProcessLine($process, $currentDomain, $spaces);

                foreach ($process['tasks'] as $taskIndex => $task) {
                    $taskIndex++;
                    $taskSpaces = $this->addTaskLine($task, $taskIndex, $currentDomain, $spaces);
                    $this->addPropertiesLine($taskSpaces, $task);
                }

                $this->addNewLine();
            }
        }
    }

    /**
     * Add a process line to the lines array.
     */
    private function addProcessLine(array $process, string $currentDomain, string $spaces): void
    {
        $processName = $process['name'];
        $inChain = $process['chain'] ? ' chained' : '.';
        $dots = str_repeat('.', max($this->terminalWidth - mb_strlen($currentDomain.$processName.$spaces.$inChain) - 5, 0));
        $dots = $dots === '' || $dots === '0' ? $dots : " $dots";

        $this->lines[] = [
            sprintf(
                '  <fg=blue;options=bold>%s</> %s<fg=blue;options=bold>%s</><fg=#6C7280>%s%s</>',
                strtoupper($currentDomain),
                $spaces,
                $processName,
                $dots,
                $inChain
            ),
        ];

    }

    /**
     * Add a properties line to the lines array.
     */
    private function addPropertiesLine(string $taskSpaces, array $task): void
    {
        if (! $this->output->isVerbose()) {
            return;
        }

        if (collect($task['properties'])->contains('output', false)) {
            $this->addProperties($task, $taskSpaces);
        }

        if (collect($task['properties'])->contains('output', true)) {
            $this->addProperties($task, $taskSpaces, true);
        }
    }

    /**
     * Add properties to the lines array.
     */
    private function addProperties(array $task, string $taskSpaces, bool $output = false): void
    {
        $this->lines[] = [
            sprintf(
                '%s   <fg=#6C7280>%s</>',
                $taskSpaces,
                $output ? 'Output' : 'Required Properties'
            ),
        ];

        foreach ($task['properties'] as $property) {
            if ($output && $property['output']) {
                continue;
            }

            $propertyIndex = '⇂ ';
            $propertyName = $property['name'];
            $propertyType = $property['type'];

            $this->lines[] = [
                sprintf(
                    '%s   <fg=white>%s%s</><fg=#6C7280>: %s</>',
                    $taskSpaces,
                    $propertyIndex,
                    $propertyName,
                    $propertyType
                ),
            ];
        }

        $this->addNewLine();
    }

    /**
     *  Add a task line to the lines array.
     */
    private function addTaskLine(array $task, int $taskIndex, string $currentDomain, string $spaces): string
    {
        $taskIndex = "{$taskIndex}. ";
        $taskName = $task['name'];
        $taskSpaces = str_repeat(' ', 3 + mb_strlen($currentDomain) + mb_strlen($spaces));
        $taskQueued = $task['queue'] ? ' queued' : '.';
        $taskDots = str_repeat('.', $this->terminalWidth - mb_strlen($taskSpaces.$taskIndex.$taskName) - mb_strlen($taskQueued) - 2);
        $taskDots = $taskDots === '' || $taskDots === '0' ? $taskDots : " $taskDots";

        $this->lines[] = [
            sprintf(
                '%s<fg=white>%s%s</><fg=#6C7280>%s%s</>',
                $taskSpaces,
                $taskIndex,
                $taskName,
                $taskDots,
                $taskQueued
            ),
        ];

        return $taskSpaces;
    }

    /**
     * Add a new line to the lines array.
     */
    private function addNewLine(): void
    {
        if (end($this->lines) === ['']) {
            return;
        }

        $this->lines[] = [''];
    }

    /**
     * Get domain with longer name to set the max width for the domain column.
     */
    private function getLengthOfTheLongestDomain(Collection $map): int
    {
        return mb_strlen(
            (string) $map->sortByDesc(fn ($value): int => mb_strlen((string) $value['domain']))
                ->first()['domain']
        );
    }

    /**
     * Get the terminal width.
     */
    private function getTerminalWidth(): void
    {
        $this->terminalWidth = (new Terminal)->getWidth();
    }

    /**
     * Get the map of domains, processes, and tasks.
     */
    private function getBrainMap(): Collection
    {
        $domains = $this->domains();
        $map = [];

        foreach ($domains as $basename => $path) {
            $map[] = [
                'domain' => $basename,
                'processes' => $this->getProcessesFor($path),
            ];
        }

        return collect($map);
    }

    /**
     * Get the list of domains.
     *
     * @return <string, string>[]
     */
    private function domains(): array
    {
        return collect(File::directories(app_path('Brain')))
            ->when($this->option('filter'), fn ($collection) => $collection->filter(fn ($value): bool => basename((string) $value) === $this->option('filter')))
            ->flatMap(fn ($value) => [basename((string) $value) => $value])
            ->toArray();
    }

    /**
     * Get the list of processes for the given domain.
     *
     * @return string[]
     */
    private function getProcessesFor(string $path): array
    {
        $path = $path.DIRECTORY_SEPARATOR.'Processes';

        return collect(File::files($path))
            ->map(function ($value): array {
                $reflection = $this->getReflectionClass($value);
                $hasChainProperty = $reflection->hasProperty('chain');
                $chainProperty = $hasChainProperty ? $reflection->getProperty('chain') : null;
                $chainValue = $chainProperty->getValue(new $reflection->name([]));

                if ($value instanceof SplFileInfo) {
                    $value = $value->getPathname();
                }

                return [
                    'name' => basename($value, '.php'),
                    'chain' => $chainValue,
                    'tasks' => collect($reflection->getProperty('tasks')->getValue(new $reflection->name([])))
                        ->map(function ($task): ?array {
                            $reflection = $this->getReflectionClass($task, true);
                            $reflection->implementsInterface(ShouldQueue::class);

                            $docBlockFactory = DocBlockFactory::createInstance();
                            $docBlock = $reflection->getDocComment();

                            if (is_bool($docBlock)) {
                                return null;
                            }

                            $classDocBlock = $docBlockFactory->create($docBlock);

                            $properties = collect($classDocBlock->getTags())
                                ->map(function (Tag $tag): ?array {
                                    if ($tag instanceof PropertyRead) {
                                        return [
                                            'name' => $tag->getVariableName(),
                                            'type' => $tag->getType(),
                                            'output' => false,
                                        ];
                                    }

                                    if ($tag instanceof Property) {
                                        return [
                                            'name' => $tag->getVariableName(),
                                            'type' => $tag->getType(),
                                            'output' => true,
                                        ];
                                    }

                                    return null;
                                })
                                ->filter()
                                ->sortBy('output')
                                ->values()
                                ->toArray();

                            return empty($properties) ? null : [
                                'name' => $reflection->getShortName(),
                                'fullName' => $reflection->name,
                                'queue' => $reflection->implementsInterface(ShouldQueue::class),
                                'properties' => $properties,
                            ];
                        })
                        ->filter()
                        ->values()
                        ->toArray(),
                ];
            })
            ->toArray();
    }

    /**
     * Get the reflection class for the given value.
     */
    private function getReflectionClass(SplFileInfo|string $value, bool $isClass = false): ReflectionClass
    {
        if ($isClass) {
            $class = $value;
        } else {
            $value = $value instanceof SplFileInfo ? $value->getPathname() : $value;
            $class = $this->getClassFullNameFromFile($value);
        }

        return new ReflectionClass($class);
    }

    /**
     * Get the full class name from a file.
     */
    private function getClassFullNameFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        return '\\'.($namespace !== '' && $namespace !== '0' ? $namespace.'\\'.$class : $class);
    }

    /**
     * Get the list of directories for the given domain.
     *
     * @return <string, string>[]
     */
    private function domainDirectories(string $path): array
    {
        return collect(File::directories($path))
            ->flatMap(fn ($value) => [basename((string) $value) => $value])
            ->toArray();
    }

    /**
     * Get the list of files for the given directory.
     *
     * @return string[]
     */
    private function files(string $path): array
    {
        return collect(File::files($path))
            ->map(fn ($value): string => basename((string) $value, '.php'))
            ->toArray();
    }
}
