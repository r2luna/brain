<?php

declare(strict_types=1);

namespace Brain\Console;

use Brain\Facades\Terminal;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;

/** Renders the brain map as a tree-style console output. */
class Printer
{
    /**
     * Colors that represent the different elements.
     */
    private array $elemColors = [
        'DOMAIN' => '#6C7280',
        'PROC' => 'blue',
        'TASK' => 'yellow',
        'QERY' => 'green',
    ];

    /**
     * @var int The width of the terminal in characters.
     */
    private int $terminalWidth;

    /**
     * @var array The lines to be printed to the terminal.
     */
    private array $lines = [];

    /** @var array Filter to show only specific element types. */
    private array $onlyTypes = [];

    /** @var string|null Case-insensitive class name filter. */
    private ?string $filter = null;

    /** Create a new Printer instance. */
    public function __construct(
        private readonly BrainMap $brain,
        private ?OutputStyle $output = null
    ) {}

    /**
     * Prints the collected lines to the output.
     */
    public function print(): void
    {
        $this->run();

        $flattenedLines = [];
        array_walk_recursive($this->lines, function ($line) use (&$flattenedLines): void {
            $flattenedLines[] = $line;
        });

        $this->output->writeln($flattenedLines);
    }

    /**
     * Sets the output style for the printer.
     */
    public function setOutput(OutputStyle $output): void
    {
        $this->output = $output;
    }

    /** Restrict output to processes only. */
    public function onlyProcesses(): self
    {
        $this->onlyTypes[] = 'process';

        return $this;
    }

    /** Restrict output to tasks only. */
    public function onlyTasks(): self
    {
        $this->onlyTypes[] = 'task';

        return $this;
    }

    /** Restrict output to queries only. */
    public function onlyQueries(): self
    {
        $this->onlyTypes[] = 'query';

        return $this;
    }

    /** Set a class name filter for the output. */
    public function filterBy(string $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Executes the main logic of the Printer class.
     */
    private function run(): void
    {
        $this->checkIfBrainMapIsEmpty();
        $this->getTerminalWidth();
        $this->createLines();
    }

    /**
     * Checks if the brain map is empty.
     *
     * @throws Exception If the brain map is empty.
     */
    private function checkIfBrainMapIsEmpty(): void
    {
        if (! $this->brain->map instanceof Collection || $this->brain->map->isEmpty()) {
            throw new Exception('The brain map is empty.');
        }
    }

    /**
     * Collects all items (processes, tasks, queries) from a domain into a flat ordered list.
     */
    private function collectDomainItems(array $domainData): array
    {
        $items = [];

        if ($this->shouldCollect('process')) {
            foreach (data_get($domainData, 'processes', []) as $process) {
                if ($this->matchesFilter($process['name'])) {
                    $items[] = ['type' => 'process', 'data' => $process];

                    continue;
                }

                if ($this->filter !== null) {
                    $matchingTasks = array_values(array_filter(
                        data_get($process, 'tasks', []),
                        fn (array $task): bool => $this->matchesFilter($task['name'])
                    ));

                    if ($matchingTasks !== []) {
                        $items[] = ['type' => 'process', 'data' => [...$process, 'tasks' => $matchingTasks]];
                    }
                }
            }
        }

        if ($this->shouldCollect('task')) {
            foreach (data_get($domainData, 'tasks', []) as $task) {
                if ($this->matchesFilter($task['name'])) {
                    $items[] = ['type' => 'task', 'data' => $task];
                }
            }
        }

        if ($this->shouldCollect('query')) {
            foreach (data_get($domainData, 'queries', []) as $query) {
                if ($this->matchesFilter($query['name'])) {
                    $items[] = ['type' => 'query', 'data' => $query];
                }
            }
        }

        return $items;
    }

    /** Determine if the given element type should be collected. */
    private function shouldCollect(string $type): bool
    {
        return $this->onlyTypes === [] || in_array($type, $this->onlyTypes);
    }

    /**
     * Creates lines for the console output.
     */
    private function createLines(): void
    {
        $useDomains = config('brain.use_domains', false);

        $this->brain->map->each(function ($domainData) use ($useDomains): void {
            $items = $this->collectDomainItems($domainData);

            if ($items === []) {
                return;
            }

            $totalItems = count($items);

            if ($useDomains) {
                $domain = data_get($domainData, 'domain', '');
                $this->lines[] = [sprintf('  <fg=%s;options=bold>%s</>', $this->elemColors['DOMAIN'], strtoupper($domain))];

                foreach ($items as $index => $item) {
                    $isLast = ($index === $totalItems - 1);
                    $this->addItemLine($item, $isLast, true);
                }
            } else {
                foreach ($items as $index => $item) {
                    $isLast = ($index === $totalItems - 1);
                    $this->addItemLine($item, $isLast, false);
                }
            }

            $this->addNewLine();
        });
    }

    /**
     * Dispatches an item to the correct type-specific method.
     */
    private function addItemLine(array $item, bool $isLast, bool $useDomains): void
    {
        if ($useDomains) {
            $connector = $isLast ? '└── ' : '├── ';
            $continuation = $isLast ? '    ' : '│   ';
            $prefix = '  '.sprintf('<fg=#6C7280>%s</>', $connector);
            $childPrefix = '  '.sprintf('<fg=#6C7280>%s</>', $continuation);
            // Raw prefix length for dot calculations (2 + 4 = 6 before TYPE)
            $prefixLen = 6;
        } else {
            $prefix = '';
            $childPrefix = '';
            $prefixLen = 0;
        }

        match ($item['type']) {
            'process' => $this->addProcessLine($item['data'], $prefix, $childPrefix, $prefixLen),
            'task' => $this->addTaskLine($item['data'], $prefix, $childPrefix, $prefixLen),
            'query' => $this->addQueryLine($item['data'], $prefix, $prefixLen),
        };
    }

    /**
     * Adds a single process line.
     */
    private function addProcessLine(array $process, string $prefix, string $childPrefix, int $prefixLen): void
    {
        $processName = data_get($process, 'name');
        $status = $process['chain'] ? ' chained' : '';

        // Visual length: prefix + "PROC" + "  " + name + " " + dots + status
        $fixedLen = $prefixLen + 4 + 2 + mb_strlen((string) $processName) + 1 + mb_strlen($status);
        $dotCount = max($this->terminalWidth - $fixedLen, 0);
        $dots = str_repeat('·', $dotCount);

        $this->lines[] = [
            sprintf(
                '%s<fg=%s;options=bold>%s</>  <fg=white>%s</><fg=#6C7280> %s%s</>',
                $prefix, $this->elemColors['PROC'], 'PROC',
                $processName, $dots, $status
            ),
        ];

        if ($this->output->isVerbose() || ($this->onlyTypes !== [] && $this->filter !== null)) {
            $this->addProcessTasks($process, $childPrefix, $prefixLen, $prefixLen);
        }
    }

    /**
     * Adds sub-tasks of a process with tree connectors.
     */
    private function addProcessTasks(array $process, string $parentChildPrefix, int $parentPrefixLen, int $prefixVisualWidth): void
    {
        $tasks = data_get($process, 'tasks', []);
        $totalTasks = count($tasks);

        foreach ($tasks as $taskIndex => $task) {
            $num = $taskIndex + 1;
            $isLastTask = ($taskIndex === $totalTasks - 1);

            $connector = $isLastTask ? '└── ' : '├── ';

            // Sub-task tree connectors start at the name column of the parent
            // Without domains: col 6 (after "PROC  ")
            // With domains: col 12 (after "  ├── PROC  ")
            $nameCol = $parentPrefixLen + 4 + 2; // prefix + TYPE(4) + spaces(2)
            $indentSpaces = str_repeat(' ', $nameCol);

            [$color, $type] = match ($task['type']) {
                'process' => [$this->elemColors['PROC'], 'P'],
                default => [$this->elemColors['TASK'], 'T'],
            };

            $status = $task['queue'] ? ' queued' : '';
            $taskName = $task['name'];

            // Full visual length including prefix width for proper right-alignment
            $visualLen = $prefixVisualWidth + $nameCol + 4 + mb_strlen("{$num}. ") + 1 + 1 + mb_strlen((string) $taskName) + 1 + mb_strlen($status);
            $dotCount = max($this->terminalWidth - $visualLen, 0);
            $dots = str_repeat('·', $dotCount);

            $this->lines[] = [
                sprintf(
                    '%s%s<fg=#6C7280>%s</><fg=white>%s</><fg=%s;options=bold>%s</> <fg=white>%s</><fg=#6C7280> %s%s</>',
                    $parentChildPrefix, $indentSpaces, $connector,
                    "{$num}. ", $color, $type, $taskName, $dots, $status
                ),
            ];

            $continuation = $isLastTask ? '    ' : '<fg=#6C7280>│</>   ';
            $subtaskChildPrefix = $parentChildPrefix.$indentSpaces.$continuation;
            $subtaskPrefixVisualWidth = $prefixVisualWidth + $nameCol + 4;

            if ($task['type'] === 'process' && ! empty($task['tasks'])) {
                $this->addProcessTasks($task, $subtaskChildPrefix, 0, $subtaskPrefixVisualWidth);
            } elseif ($this->output->isVeryVerbose()) {
                $this->addProperties($task, $subtaskChildPrefix, 3);
            }
        }
    }

    /**
     * Adds a single task line.
     */
    private function addTaskLine(array $task, string $prefix, string $childPrefix, int $prefixLen): void
    {
        $taskName = $task['name'];
        $status = $task['queue'] ? ' queued' : '';

        // Visual: prefix + "TASK" + "  " + name + " " + dots + status
        $fixedLen = $prefixLen + 4 + 2 + mb_strlen((string) $taskName) + 1 + mb_strlen($status);
        $dotCount = max($this->terminalWidth - $fixedLen, 0);
        $dots = str_repeat('·', $dotCount);

        $this->lines[] = [
            sprintf(
                '%s<fg=%s;options=bold>%s</>  <fg=white>%s</><fg=#6C7280> %s%s</>',
                $prefix, $this->elemColors['TASK'], 'TASK',
                $taskName, $dots, $status
            ),
        ];

        if ($this->output->isVeryVerbose()) {
            // Properties indented to name column + 3 spaces
            $this->addProperties($task, $childPrefix, $prefixLen + 4 + 2 + 3);
        }
    }

    /**
     * Adds property lines for a task.
     */
    private function addProperties(array $task, string $parentPrefix, int $indentSize): void
    {
        $indent = str_repeat(' ', $indentSize);

        foreach ($task['properties'] as $property) {
            $arrow = $property['direction'] === 'output' ? '→ ' : '← ';
            $color = $property['direction'] === 'output' ? '#A3BE8C' : 'white';
            $propertyName = $property['name'];
            $propertyType = $property['type'];

            $this->lines[] = [
                sprintf(
                    '%s%s<fg=%s>%s%s</><fg=#6C7280>: %s</>',
                    $parentPrefix, $indent, $color, $arrow, $propertyName, $propertyType
                ),
            ];
        }
    }

    /**
     * Adds a single query line.
     */
    private function addQueryLine(array $query, string $prefix, int $prefixLen): void
    {
        $queryName = $query['name'];

        // Visual: prefix + "QERY" + "  " + name + " " + dots
        $fixedLen = $prefixLen + 4 + 2 + mb_strlen((string) $queryName) + 1;
        $dotCount = max($this->terminalWidth - $fixedLen, 0);
        $dots = str_repeat('·', $dotCount);

        $this->lines[] = [
            sprintf(
                '%s<fg=%s;options=bold>%s</>  <fg=white>%s</> <fg=#6C7280>%s</>',
                $prefix, $this->elemColors['QERY'], 'QERY',
                $queryName, $dots
            ),
        ];
    }

    /**
     * Adds a new empty line to the lines array if the last line is not already empty.
     */
    private function addNewLine(): void
    {
        if (end($this->lines) === ['']) {
            return;
        }

        $this->lines[] = [''];
    }

    /** Check if the given name matches the current filter. */
    private function matchesFilter(string $name): bool
    {
        if ($this->filter === null) {
            return true;
        }

        return str_contains(mb_strtolower($name), mb_strtolower($this->filter));
    }

    /**
     * Retrieves and sets the terminal's current width.
     */
    private function getTerminalWidth(): void
    {
        $this->terminalWidth = Terminal::cols();
    }
}
