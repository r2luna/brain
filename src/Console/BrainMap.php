<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\File;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;

/**
 * Class BrainMap
 *
 * This class is responsible for mapping and managing the domains, processes, tasks,
 * and queries within the Brain application. It loads metadata for each domain
 * directory under the `Brain` namespace and provides methods to retrieve these details.
 *
 * The `BrainMap` class involves scanning directories, reflecting on PHP class files,
 * and extracting relevant metadata using PHP Reflection API alongside custom logic.
 *
 * Responsibilities:
 * - Load and map domain directories.
 * - Fetch metadata for processes, tasks, and queries within each domain.
 * - Utilize reflection to analyze PHP class structure and properties.
 *
 * Properties:
 * - `domains`: An array containing mappings of loaded domains with their metadata.
 *
 * Methods:
 * - `__construct()`: Initializes the class and loads the domains.
 * - `loadDomains()`: Loads all available domains and their respective components.
 * - `loadProcessesFor(string $domainPath)`: Retrieves process metadata for a given domain path.
 * - `loadTasksFor(string $domainPath)`: Retrieves task metadata for a given domain path.
 * - `loadQueriesFor(string $domainPath)`: Retrieves query metadata for a given domain path.
 * - `getPropertiesFor(ReflectionClass $reflection)`: Extracts properties metadata for a given class through docblock parsing.
 * - `getReflectionClass(SplFileInfo|string $value)`: Creates and returns a ReflectionClass instance for a given file or class.
 */
class BrainMap
{
    protected array $domains;

    /**
     * Constructs a new instance of the BrainMap class and initializes the loaded domains.
     *
     * Upon construction, the class automatically invokes the `loadDomains` method
     * to populate the `$domains` property with metadata for each domain in the application.
     */
    public function load(): void
    {
        $domains = collect(File::directories(app_path('Brain')))
            ->flatMap(fn($value) => [basename((string) $value) => $value])
            ->map(fn($domainPath, $domain) => [
                'domain' => $domain,
                'path' => $domainPath,
                'processes' => $this->loadProcessesFor($domainPath),
                'tasks' => $this->loadTasksFor($domainPath),
                'queries' => $this->loadQueriesFor($domainPath),
            ])
            ->toArray();

        $this->domains = $domains;
    }

    public function getProcessesTasks(ReflectionClass $process): array
    {
        return collect($process->getProperty('tasks')->getValue(new $process->name([])))
            ->map(fn(string $task): array => $this->getTask($task))
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Retrieves the array of all loaded domains with their associated metadata.
     *
     * Each domain includes information about its processes, tasks, and queries.
     *
     * @return array Returns an associative array where each key is the domain name,
     *               and the value is an array containing:
     *               - `domain`: The domain name.
     *               - `path`: The path to the domain directory.
     *               - `processes`: An array of process metadata, such as name, chain, and tasks.
     *               - `tasks`: An array of task metadata, including task properties and queue status.
     *               - `queries`: An array of query metadata with class properties.
     */
    private function loadProcessesFor(string $domainPath): array
    {
        $path = $domainPath . DIRECTORY_SEPARATOR . 'Processes';

        if (! is_dir($path)) {
            return [];
        }

        return collect(File::files($path))
            ->map(function (SplFileInfo $value): array {
                $reflection = $this->getReflectionClass($value);
                $hasChainProperty = $reflection->hasProperty('chain');
                $chainProperty = $hasChainProperty ? $reflection->getProperty('chain') : null;
                $chainValue = $chainProperty->getValue(new $reflection->name([]));
                $value = $value->getPathname();

                return [
                    'name' => basename($value, '.php'),
                    'chain' => $chainValue,
                    'tasks' => $this->getProcessesTasks($reflection),
                ];
            })
            ->toArray();
    }

    /**
     * Loads tasks for a specific domain path.
     *
     * This method scans the directory named `Tasks` under the given domain path
     * and retrieves metadata information about each task file. It uses reflection
     * to parse the class details and gathers properties, queue implementation, and
     * class names.
     *
     * @param  string  $domainPath  The absolute path to the domain directory.
     * @return array Returns an array of associative arrays. Each entry contains:
     *               - `name`: The short class name of the task.
     *               - `fullName`: The fully qualified class name.
     *               - `queue`: Whether the class implements the `ShouldQueue` interface (boolean).
     *               - `properties`: A list of properties metadata for the class, if available.
     */
    private function loadTasksFor(string $domainPath): array
    {
        $path = $domainPath . DIRECTORY_SEPARATOR . 'Tasks';

        if (! is_dir($path)) {
            return [];
        }

        return collect(File::files($path))
            ->map(fn(SplFileInfo $task): array => $this->getTask($task))
            ->toArray();
    }

    /**
     * Retrieves task details from the given file.
     *
     * @param  SplFileInfo|string  $task  The file containing the task class.
     * @return array An associative array containing:
     *               - 'name': The short name of the class.
     *               - 'fullName': The fully qualified name of the class.
     *               - 'queue': A boolean indicating if the class implements the ShouldQueue interface.
     *               - 'properties': An array of properties for the class.
     */
    private function getTask(SplFileInfo|string $task): array
    {
        $reflection = $this->getReflectionClass($task);

        return [
            'name' => $reflection->getShortName(),
            'fullName' => $reflection->name,
            'queue' => $reflection->implementsInterface(ShouldQueue::class),
            'properties' => $this->getPropertiesFor($reflection),
        ];
    }

    /**
     * Retrieves an array of property metadata derived from the docblock of the given class.
     *
     * @param  ReflectionClass  $reflection  The reflection class instance for which properties need to be extracted.
     * @return array|null Returns an array containing property metadata, or null if the docblock is invalid or unavailable.
     */
    private function getPropertiesFor(ReflectionClass $reflection): ?array
    {
        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock = $reflection->getDocComment();

        if (is_bool($docBlock)) {
            return [];
        }

        $classDocBlock = $docBlockFactory->create($docBlock);

        return collect($classDocBlock->getTags())
            ->map(function (Tag $tag): ?array {
                if ($tag instanceof PropertyRead) {
                    return [
                        'name' => $tag->getVariableName(),
                        'type' => $tag->getType()->__toString(),
                        'direction' => 'output',
                    ];
                }

                if ($tag instanceof Property) {
                    return [
                        'name' => $tag->getVariableName(),
                        'type' => $tag->getType()->__toString(),
                        'direction' => 'input',
                    ];
                }

                return null;
            })
            ->filter()
            ->sortByDesc('direction')
            ->values()
            ->toArray();
    }

    /**
     * Loads and processes query files from a specified domain path.
     *
     * @param  string  $domainPath  The path to the domain directory containing query files.
     * @return array An array containing details of each query, such as its name,
     *               full class name, and properties.
     */
    private function loadQueriesFor(string $domainPath): array
    {
        $path = $domainPath . DIRECTORY_SEPARATOR . 'Queries';

        if (! is_dir($path)) {
            return [];
        }

        return collect(File::files($path))
            ->map(function ($task): array {
                $reflection = $this->getReflectionClass($task);

                $properties = [];
                $constructor = $reflection->getConstructor();

                if ($constructor) {
                    $parameters = $constructor->getParameters();

                    foreach ($parameters as $parameter) {
                        $properties[] = [
                            'name' => $parameter->getName(),
                            'type' => $parameter->getType() ? $parameter->getType()->getName() : 'mixed',
                        ];
                    }
                }

                return [
                    'name' => $reflection->getShortName(),
                    'fullName' => $reflection->name,
                    'properties' => $properties,
                ];
            })
            ->toArray();
    }

    // region Helper Methods

    /**
     * Creates a ReflectionClass instance for a given class or file.
     *
     * @param  SplFileInfo|string  $value  The file path or SplFileInfo object representing the class.
     * @return ReflectionClass Returns a ReflectionClass instance for the resolved class.
     *
     * @throws ReflectionException if the class does not exist or cannot be resolved.
     */
    private function getReflectionClass(SplFileInfo|string $value): ReflectionClass
    {
        if (is_string($value)) {
            $class = $value;
        } else {
            $value = $value instanceof SplFileInfo ? $value->getPathname() : $value;
            $class = $this->getClassFullNameFromFile($value);
        }

        return new ReflectionClass($class);
    }

    /**
     * Retrieves the full class name, including namespace, from a given file path.
     *
     * This method reads the file and uses regular expressions to extract the namespace
     * and class name, ultimately combining them into a fully qualified class name.
     *
     * @param  string  $filePath  The path to the file containing the class definition.
     * @return string Returns the fully qualified class name (namespace + class name).
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

        return '\\' . ($namespace !== '' && $namespace !== '0' ? $namespace . '\\' . $class : $class);
    }

    // endregion
}
