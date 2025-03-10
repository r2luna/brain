<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Event\Runtime\PHP;
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
    protected $signature = '
        brain:show
        {--domain=}
        {--process=}
        {--task=}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Brain Mapping';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $map = $this->getBrainMap();

        ds($map);

        $this->displayBrain($map);
    }

    private function displayBrain(Collection $map): void
    {
        /**
         * php artisan brain:show
            PAYMENTS    ProcessPaymentProcess ............................................ chained
                        1. ProcessPaymentTask .............................................. queued
                        2. SendPaymentEmailTask ...................................................
                        3. NotifyStaffTask ........................................................

            USER        RegisterUserProcess .......................................................
                        1.  RegisterUserTask ......................................................
                        Properties:
                        ⇂ name: string
                        ⇂ email: string
                        ⇂ password: string

                        Output:
                        ⇂ user: User

                        2. SendWelcomeEmailTask .................................... queued.default
                        3. NotifyStaffTask ................................................. queued
         */
        $lines = [];
        $terminalWidth = $this->getTerminalWidth();
        $tasksInUse = [];

        foreach ($map as $domain) {
            $currentDomain = $domain['domain'];
            $maxDomain = mb_strlen($map->sortByDesc(fn ($value) => mb_strlen($value['domain']))->first()['domain']);

            foreach ($domain['processes'] as $process) {
                $spaces = $this->printProcess($lines, $process, $maxDomain, $currentDomain, $terminalWidth);

                foreach ($process['tasks'] as $taskIndex => $task) {
                    $tasksInUse[] = $task['fullName'];

                    $taskSpaces = $this->printTask($lines, $task, $currentDomain, $spaces, $terminalWidth, $taskIndex);

                }
            }
        }

        $this->output->writeln(
            collect($lines)->flatten()
        );
    }

    /**
     * Print Process
     */
    private function printProcess(array &$lines, array $process, string $maxDomain, string $currentDomain, int $terminalWidth): string
    {
        $spaces = str_repeat(' ', max($maxDomain + 4 - mb_strlen($currentDomain), 0));
        $processName = $process['name'];
        $inChain = $process['chain'] ? ' chained' : '.';

        $dots = str_repeat('.', max(
            $terminalWidth - mb_strlen($currentDomain.$processName.$spaces.$inChain) - 5,
            0
        ));

        $dots = empty($dots) ? $dots : " $dots";

        $lines[] = [
            sprintf(
                '  <fg=blue;options=bold>%s</> %s<fg=yellow>%s</><fg=#6C7280>%s%s</>',
                strtoupper($currentDomain),
                $spaces,
                $processName,
                $dots,
                $inChain
            ),
        ];

        return $spaces;
    }

    /**
     * Print task
     */
    private function printTask(array &$lines, array $task, string $currentDomain, string $spaces, int $terminalWidth, int $taskIndex): string
    {
        $taskIndex++;
        $taskIndex = "{$taskIndex}. ";
        $taskName = $task['name'];
        $taskSpaces = str_repeat(' ', 3 + mb_strlen($currentDomain) + mb_strlen($spaces));
        $taskQueued = $task['queue'] ? ' queued' : '.';
        $taskDots = str_repeat('.', $terminalWidth - mb_strlen($taskSpaces.$taskIndex.$taskName) - mb_strlen($taskQueued) - 2);
        $taskDots = empty($taskDots) ? $taskDots : " $taskDots";

        $lines[] = [
            sprintf(
                '%s<fg=white>%s%s</><fg=#6C7280>%s%s</>',
                $taskSpaces,
                $taskIndex,
                $taskName,
                $taskDots,
                $taskQueued
            ),
        ];

        if ($this->output->isVerbose()) {

            $lines[] = [
                sprintf(
                    '%s   <fg=#6C7280>%s</>',
                    $taskSpaces,
                    'Properties'
                ),
            ];

            foreach ($task['properties'] as $property) {
                if ($property['output']) {
                    continue;
                }

                $this->printProperty($lines, $property, $taskSpaces);
            }

            if (collect($task['properties'])->where('output', true)->count() > 0) {
                $lines[] = [''];
                $lines[] = [
                    sprintf(
                        '%s   <fg=#6C7280>%s</>',
                        $taskSpaces,
                        'Output'
                    ),
                ];

                foreach ($task['properties'] as $property) {
                    if (! $property['output']) {
                        continue;
                    }

                    $this->printProperty($lines, $property, $taskSpaces);
                }

            }
            $lines[] = [''];
        }

        return $taskSpaces;
    }

    /**
     * Print property tasks
     */
    private function printProperty(array &$lines, array $property, string $taskSpaces): void
    {
        /**
        1.  RegisterUserTask ......................................................
        Properties:
        ⇂ name: string
        ⇂ email: string
        ⇂ password: string

        Output:
        ⇂ user: User
         */
        $propertyName = $property['name'];
        $propertyType = $property['type'];

        $lines[] = [
            sprintf(
                '%s   <fg=white>%s%s</><fg=#6C7280>: %s</>',
                $taskSpaces,
                '⇂ ',
                $propertyName,
                $propertyType
            ),
        ];
    }

    /**
     * Get the terminal width.
     */
    private static function getTerminalWidth(): int
    {
        return (new Terminal)->getWidth();
    }

    /**
     * Get the map of domains, processes, and tasks.
     */
    private function getBrainMap(): Collection
    {
        $domains = $this->getDomains();
        $map = [];

        foreach ($domains as $basename => $path) {
            $map[] = [
                'domain' => $basename,
                'processes' => $this->getProcessesFor($path),
                'tasks' => $this->getTasksFor($path),
            ];
        }

        return collect($map);
    }

    /**
     * Get the list of domains.
     *
     * @return <string, string>[]
     */
    private function getDomains(): array
    {
        return collect(File::directories(app_path('Brain')))
            ->when($this->option('domain'), fn ($collection) => $collection->filter(fn ($value) => basename($value) === $this->option('domain')))
            ->flatMap(fn ($value) => [basename($value) => $value])
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

                return [
                    'name' => basename($value, '.php'),
                    'chain' => $chainValue,
                    'tasks' => collect($reflection->getProperty('tasks')->getValue(new $reflection->name([])))
                        ->map(fn ($task) => $this->getTask($task))
                        ->filter()
                        ->when($this->option('task'), fn ($collection) => $collection->filter(fn ($value) => $value['name'] === $this->option('task')))
                        ->values()
                        ->toArray(),
                ];
            })
            ->when($this->option('process'), fn ($collection) => $collection->filter(fn ($value) => $value['name'] === $this->option('process')))
            ->toArray();
    }

    private function getTask(SplFileInfo|string $task): ?array
    {
        $reflection = $this->getReflectionClass($task);
        $reflection->implementsInterface(ShouldQueue::class);

        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock = $reflection->getDocComment();

        if (is_bool($docBlock)) {
            return null;
        }

        $classDocBlock = $docBlockFactory->create($docBlock);

        $properties = collect($classDocBlock->getTags())
            ->map(function (Tag $tag) {
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

        return [
            'name' => $reflection->getShortName(),
            'fullName' => $reflection->name,
            'queue' => $reflection->implementsInterface(ShouldQueue::class),
            'properties' => $properties,
        ];
    }

    private function getTasksFor(string $domain): array
    {
        $path = $domain.DIRECTORY_SEPARATOR.'Tasks';

        return collect(File::files($path))
            ->map(fn ($task) => $this->getTask($task))
            ->filter()
            ->when($this->option('task'), fn ($collection) => $collection->filter(fn ($value) => $value['name'] === $this->option('task')))
            ->values()
            ->toArray();
    }

    /**
     * Get the reflection class for the given value.
     */
    private function getReflectionClass(SplFileInfo|string $value): ReflectionClass
    {
        if (is_string($value)) {
            $class = $value;
        } else {
            $value = $value instanceof SplFileInfo ? $value->getPathname() : $value;
            $class = $this->getClassFullNameFromFile($value);
        }

        $reflection = new ReflectionClass($class);

        return $reflection;
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

        $return = '\\'.($namespace ? $namespace.'\\'.$class : $class);

        return $return;
    }
}
