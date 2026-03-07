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
        'FLOW' => 'blue',
        'ACTN' => 'yellow',
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

    /** Restrict output to workflows only. */
    public function onlyWorkflows(): self
    {
        $this->onlyTypes[] = 'workflow';

        return $this;
    }

    /** Restrict output to actions only. */
    public function onlyActions(): self
    {
        $this->onlyTypes[] = 'action';

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

        if ($this->shouldCollect('workflow')) {
            foreach (data_get($domainData, 'workflows', []) as $workflow) {
                if ($this->matchesFilter($workflow['name'])) {
                    $items[] = ['type' => 'workflow', 'data' => $workflow];

                    continue;
                }

                if ($this->filter !== null) {
                    $matchingActions = array_values(array_filter(
                        data_get($workflow, 'tasks', []),
                        fn (array $action): bool => $this->matchesFilter($action['name'])
                    ));

                    if ($matchingActions !== []) {
                        $items[] = ['type' => 'workflow', 'data' => [...$workflow, 'tasks' => $matchingActions]];
                    }
                }
            }
        }

        if ($this->shouldCollect('action')) {
            foreach (data_get($domainData, 'actions', []) as $action) {
                if ($this->matchesFilter($action['name'])) {
                    $items[] = ['type' => 'action', 'data' => $action];
                }
            }
        }

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

            if ($useDomains) {
                $domain = data_get($domainData, 'domain', '');
                $this->lines[] = [sprintf('  <fg=%s;options=bold>%s</>', $this->elemColors['DOMAIN'], strtoupper($domain))];

                $this->renderGroupedItems($items, true);
            } else {
                $this->renderGroupedItems($items, false);
            }

            $this->addNewLine();
        });
    }

    /** Render items, grouping by subdirectory when present. */
    private function renderGroupedItems(array $items, bool $useDomains): void
    {
        $ungrouped = array_values(array_filter($items, fn (array $item): bool => data_get($item, 'data.group') === null));
        $grouped = [];

        foreach ($items as $item) {
            $group = data_get($item, 'data.group');

            if ($group !== null) {
                $grouped[$group][] = $item;
            }
        }

        $totalUngrouped = count($ungrouped);
        $hasGroups = $grouped !== [];

        foreach ($ungrouped as $index => $item) {
            $isLast = ($index === $totalUngrouped - 1) && ! $hasGroups;
            $this->addItemLine($item, $isLast, $useDomains);
        }

        if ($ungrouped !== [] && $hasGroups) {
            $this->addNewLine();
        }

        $groupNames = array_keys($grouped);
        $totalGroups = count($groupNames);

        foreach ($groupNames as $groupIndex => $groupName) {
            $groupItems = $grouped[$groupName];
            $isLastGroup = ($groupIndex === $totalGroups - 1);

            $indent = $useDomains ? '  ' : '';
            $this->lines[] = [sprintf('%s<fg=%s;options=bold>%s</>', $indent, $this->elemColors['DOMAIN'], strtoupper((string) $groupName))];

            $totalGroupItems = count($groupItems);

            foreach ($groupItems as $itemIndex => $item) {
                $isLast = ($itemIndex === $totalGroupItems - 1);
                $this->addItemLine($item, $isLast, true);
            }

            if (! $isLastGroup) {
                $this->addNewLine();
            }
        }
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
            'workflow' => $this->addWorkflowLine($item['data'], $prefix, $childPrefix, $prefixLen),
            'action' => $this->addActionLine($item['data'], $prefix, $childPrefix, $prefixLen),
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
     * Adds a single workflow line.
     */
    private function addWorkflowLine(array $workflow, string $prefix, string $childPrefix, int $prefixLen): void
    {
        $workflowName = data_get($workflow, 'name');
        $status = $workflow['chain'] ? ' chained' : '';

        $fixedLen = $prefixLen + 4 + 2 + mb_strlen((string) $workflowName) + 1 + mb_strlen($status);
        $dotCount = max($this->terminalWidth - $fixedLen, 0);
        $dots = str_repeat('·', $dotCount);

        $this->lines[] = [
            sprintf(
                '%s<fg=%s;options=bold>%s</>  <fg=white>%s</><fg=#6C7280> %s%s</>',
                $prefix, $this->elemColors['FLOW'], 'FLOW',
                $workflowName, $dots, $status
            ),
        ];

        if ($this->output->isVerbose() || ($this->onlyTypes !== [] && $this->filter !== null)) {
            $this->addProcessTasks($workflow, $childPrefix, $prefixLen, $prefixLen);
        }
    }

    /**
     * Adds a single action line.
     */
    private function addActionLine(array $action, string $prefix, string $childPrefix, int $prefixLen): void
    {
        $actionName = $action['name'];
        $status = $action['queue'] ? ' queued' : '';

        $fixedLen = $prefixLen + 4 + 2 + mb_strlen((string) $actionName) + 1 + mb_strlen($status);
        $dotCount = max($this->terminalWidth - $fixedLen, 0);
        $dots = str_repeat('·', $dotCount);

        $this->lines[] = [
            sprintf(
                '%s<fg=%s;options=bold>%s</>  <fg=white>%s</><fg=#6C7280> %s%s</>',
                $prefix, $this->elemColors['ACTN'], 'ACTN',
                $actionName, $dots, $status
            ),
        ];

        if ($this->output->isVeryVerbose()) {
            $this->addProperties($action, $childPrefix, $prefixLen + 4 + 2 + 3);
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
                'workflow' => [$this->elemColors['FLOW'], 'W'],
                'process' => [$this->elemColors['PROC'], 'P'],
                'action' => [$this->elemColors['ACTN'], 'A'],
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

            if (($task['type'] === 'process' || $task['type'] === 'workflow') && ! empty($task['tasks'])) {
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
            $arrow = $property['direction'] === 'output' ? '← ' : '→ ';
            $color = $property['direction'] === 'output' ? '#A3BE8C' : 'white';
            $propertyName = $property['name'];
            $propertyType = $property['type'];
            $sensitive = empty($property['sensitive']) ? '' : ' <fg=red>[sensitive]</>';

            $this->lines[] = [
                sprintf(
                    '%s%s<fg=%s>%s%s</><fg=#6C7280>: %s</>%s',
                    $parentPrefix, $indent, $color, $arrow, $propertyName, $propertyType, $sensitive
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
