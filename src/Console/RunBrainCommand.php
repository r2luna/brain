<?php

declare(strict_types=1);

namespace Brain\Console;

use Brain\Console\Support\PropertyInput;
use Brain\Console\Support\RunHistory;
use Brain\Process;
use Brain\SensitiveValue;
use Brain\Task;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/** Interactive command to select and run a Brain Process or Task. */
class RunBrainCommand extends Command
{
    /** @var string */
    protected $signature = 'brain:run {--rerun : Rerun a previous execution from history}';

    /** @var string */
    protected $description = 'Interactively run a Brain Process or Task';

    /** Format a value for table display, unwrapping sensitive values. */
    public static function formatValue(mixed $value): string
    {
        if ($value instanceof SensitiveValue) {
            $value = $value->value();
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    public function handle(): int
    {
        if ($this->option('rerun')) {
            return $this->handleRerun();
        }

        $brainMap = new BrainMap;
        $targets = $this->collectTargets($brainMap);

        if ($targets === []) {
            warning('No Processes or Tasks found in your Brain directory.');

            return self::FAILURE;
        }

        $target = $this->selectTarget($targets);
        $sync = $this->selectDispatchMode();
        $payload = $this->collectPayload($target['properties'] ?? []);

        if (! $this->preview($target, $payload, $sync)) {
            note('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $this->runTarget($target, $payload, $sync);
        } catch (Throwable $e) {
            error($e::class);
            warning($e->getMessage());

            return self::FAILURE;
        }

        $this->saveToHistory($target, $payload, $sync);
        $this->displayResult($result, $sync);

        return self::SUCCESS;
    }

    /** Collect all Processes and Tasks from the brain map. */
    private function collectTargets(BrainMap $brainMap): array
    {
        $targets = [];

        foreach ($brainMap->map as $domainData) {
            foreach (data_get($domainData, 'processes', []) as $process) {
                $properties = $this->aggregateProcessProperties($process);

                $targets[$process['fullName']] = [
                    'class' => $process['fullName'],
                    'name' => $process['name'],
                    'type' => 'process',
                    'properties' => $properties,
                ];
            }

            foreach (data_get($domainData, 'tasks', []) as $task) {
                if ($task['type'] === 'process') {
                    continue; // @codeCoverageIgnore
                }

                $targets[$task['fullName']] = [
                    'class' => $task['fullName'],
                    'name' => $task['name'],
                    'type' => 'task',
                    'properties' => $task['properties'] ?? [],
                ];
            }
        }

        return $targets;
    }

    /** Aggregate unique properties from all sub-tasks of a process, plus the process's own properties. */
    private function aggregateProcessProperties(array $process): array
    {
        $properties = [];
        $seen = [];

        foreach ($process['properties'] ?? [] as $prop) { // @codeCoverageIgnoreStart
            if (! isset($seen[$prop['name']])) {
                $properties[] = $prop;
                $seen[$prop['name']] = true;
            }
        } // @codeCoverageIgnoreEnd

        foreach ($process['tasks'] ?? [] as $task) {
            foreach ($task['properties'] ?? [] as $prop) { // @codeCoverageIgnoreStart
                if (! isset($seen[$prop['name']])) {
                    $properties[] = $prop;
                    $seen[$prop['name']] = true;
                }
            } // @codeCoverageIgnoreEnd
        }

        return $properties;
    }

    /** Let the user search and select a Process or Task. */
    private function selectTarget(array $targets): array
    {
        $options = [];
        foreach ($targets as $fqcn => $target) {
            $label = strtoupper((string) $target['type']).'  '.$target['name'];
            $options[$fqcn] = $label;
        }

        $selected = search(
            label: 'What do you want to run?',
            options: function (string $query) use ($options): array {
                if ($query === '') {
                    return $options; // @codeCoverageIgnore
                }

                return array_filter(
                    $options,
                    fn (string $label): bool => str_contains(
                        mb_strtolower($label),
                        mb_strtolower($query),
                    ),
                );
            },
        );

        return $targets[$selected];
    }

    /** Let the user choose sync or async dispatch. */
    private function selectDispatchMode(): bool
    {
        return select(
            label: 'How should it be dispatched?',
            options: [
                'sync' => 'Sync (dispatchSync)',
                'async' => 'Async (dispatch)',
            ],
        ) === 'sync';
    }

    /**
     * Prompt the user for each property value.
     *
     * @param  array<int, array{name: string, type: string, direction: string, sensitive: bool}>  $properties
     */
    private function collectPayload(array $properties): array
    {
        if ($properties === []) {
            return [];
        }

        $payload = [];
        $required = array_filter($properties, fn (array $p): bool => $p['direction'] === 'output');
        $optional = array_filter($properties, fn (array $p): bool => $p['direction'] === 'input');

        if ($required !== []) {
            note('Required properties:');

            foreach ($required as $property) {
                $payload[$property['name']] = PropertyInput::prompt($property);
            }
        }

        if ($optional !== [] && confirm('Fill optional properties?', false)) {
            foreach ($optional as $property) {
                $payload[$property['name']] = PropertyInput::prompt($property);
            }
        }

        return $payload;
    }

    /** Show a preview of the dispatch call and ask for confirmation. */
    private function preview(array $target, array $payload, bool $sync): bool
    {
        $method = $sync ? 'dispatchSync' : 'dispatch';
        $shortClass = class_basename($target['class']);

        if ($payload !== []) {
            $displayPayload = $this->maskSensitiveValues($target, $payload);
            $rows = array_map(
                fn (string $key, mixed $value): array => [$key, self::formatValue($value)],
                array_keys($displayPayload),
                array_values($displayPayload),
            );

            table(['Property', 'Value'], $rows);
        }

        info("{$shortClass}::{$method}(...)");

        return confirm('Execute?', true);
    }

    /** Execute the dispatch and return the result. */
    private function runTarget(array $target, array $payload, bool $sync): mixed
    {
        $class = $target['class'];

        return spin(
            callback: fn (): mixed => $sync
                ? $class::dispatchSync($payload)
                : $class::dispatch($payload),
            message: 'Running...',
        );
    }

    /** Display the result of the dispatch. */
    private function displayResult(mixed $result, bool $sync): void
    {
        if (! $sync) {
            info('Dispatched to queue.');

            return;
        }

        if ($result instanceof Task || $result instanceof Process) {
            $result->finalize();
            $payload = (array) $result->payload;

            if ($payload !== []) {
                $rows = array_map(
                    fn (string $key, mixed $value): array => [$key, self::formatValue($value)],
                    array_keys($payload),
                    array_values($payload),
                );

                table(['Property', 'Value'], $rows);
            }

            info('Done.');

            return;
        }

        if (is_object($result)) { // @codeCoverageIgnoreStart
            $payload = (array) $result;

            if ($payload !== []) {
                $rows = array_map(
                    fn (string $key, mixed $value): array => [$key, self::formatValue($value)],
                    array_keys($payload),
                    array_values($payload),
                );

                table(['Property', 'Value'], $rows);
            }
        } // @codeCoverageIgnoreEnd

        info('Done.');
    }

    /** Replace sensitive property values with asterisks for preview display. */
    private function maskSensitiveValues(array $target, array $payload): array
    {
        $sensitiveKeys = collect($target['properties'] ?? [])
            ->filter(fn (array $p): bool => $p['sensitive'] ?? false)
            ->pluck('name')
            ->all();

        $masked = $payload;

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $masked)) {
                $masked[$key] = '********';
            }
        }

        return $masked;
    }

    /** Handle the --rerun flow: select a previous run and replay it. */
    private function handleRerun(): int
    {
        $entries = RunHistory::default()->all();

        if ($entries === []) {
            warning('No previous runs found.');

            return self::SUCCESS;
        }

        $entry = $this->selectFromHistory($entries);

        $target = [
            'class' => $entry['class'],
            'type' => $entry['type'],
        ];
        $payload = $entry['payload'];
        $sync = $entry['sync'];

        if (! $this->preview($target, $payload, $sync)) {
            note('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $this->runTarget($target, $payload, $sync);
        } catch (Throwable $e) {
            error($e::class);
            warning($e->getMessage());

            return self::FAILURE;
        }

        $this->saveToHistory($target, $payload, $sync);
        $this->displayResult($result, $sync);

        return self::SUCCESS;
    }

    /** Let the user search and select from history entries. */
    private function selectFromHistory(array $entries): array
    {
        $mapped = [];
        foreach ($entries as $entry) {
            $mode = $entry['sync'] ? 'sync' : 'async';
            $shortClass = class_basename($entry['class']);
            $label = strtoupper((string) $entry['type']).'  '.$shortClass.' ('.$mode.') — '.$entry['timestamp'];
            $mapped[$label] = $entry;
        }

        $options = array_keys($mapped);

        $selected = search(
            label: 'Select a previous run:',
            options: function (string $query) use ($options): array {
                if ($query === '') {
                    return $options; // @codeCoverageIgnore
                }

                return array_filter(
                    $options,
                    fn (string $label): bool => str_contains(
                        mb_strtolower($label),
                        mb_strtolower($query),
                    ),
                );
            },
        );

        return $mapped[$selected];
    }

    /** Save a successful run to the history file. */
    private function saveToHistory(array $target, array $payload, bool $sync): void
    {
        $unwrapped = array_map(
            fn (mixed $value): mixed => $value instanceof SensitiveValue ? $value->value() : $value,
            $payload,
        );

        RunHistory::default()->record($target, $unwrapped, $sync);
    }
}
