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
use SplFileInfo;

class BrainMap
{
    protected array $domains;

    public function __construct()
    {
        $this->loadDomains();
    }

    private function loadDomains(): void
    {
        $domains = collect(File::directories(app_path('Brain')))
            ->flatMap(fn ($value) => [basename((string) $value) => $value])
            ->map(fn ($domainPath, $domain) => [
                'domain' => $domain,
                'path' => $domainPath,
                'processes' => $this->loadProcessesFor($domainPath),
                'tasks' => $this->loadTasksFor($domainPath),
            ])
            ->toArray();

        $this->domains = $domains;
    }

    private function loadProcessesFor(string $domainPath): array
    {
        $path = $domainPath.DIRECTORY_SEPARATOR.'Processes';

        return collect(File::files($path))
            ->map(function (SplFileInfo $value) use ($domainPath): array {
                $reflection = $this->getReflectionClass($value);
                $hasChainProperty = $reflection->hasProperty('chain');
                $chainProperty = $hasChainProperty ? $reflection->getProperty('chain') : null;
                $chainValue = $chainProperty->getValue(new $reflection->name([]));
                $value = $value->getPathname();

                return [
                    'name' => basename($value, '.php'),
                    'chain' => $chainValue,
                    'tasks' => $this->loadTasksFor($domainPath),
                ];
            })
            ->toArray();
    }

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

    private function loadTasksFor(string $domainPath): array
    {
        $path = $domainPath.DIRECTORY_SEPARATOR.'Tasks';

        return collect(File::files($path))
            ->map(function ($task): array {
                $reflection = $this->getReflectionClass($task);

                return [
                    'name' => $reflection->getShortName(),
                    'fullName' => $reflection->name,
                    'queue' => $reflection->implementsInterface(ShouldQueue::class),
                    'properties' => $this->getPropertiesFor($reflection),
                ];
            })
            ->toArray();
    }

    private function getPropertiesFor(ReflectionClass $reflection): ?array
    {
        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock = $reflection->getDocComment();

        if (is_bool($docBlock)) {
            return null;
        }

        $classDocBlock = $docBlockFactory->create($docBlock);

        return collect($classDocBlock->getTags())
            ->map(function (Tag $tag): ?array {
                if ($tag instanceof PropertyRead) {
                    return [
                        'name' => $tag->getVariableName(),
                        'type' => $tag->getType(),
                        'direction' => 'output',
                    ];
                }

                if ($tag instanceof Property) {
                    return [
                        'name' => $tag->getVariableName(),
                        'type' => $tag->getType(),
                        'direction' => 'input',
                    ];
                }

                return null;
            })
            ->filter()
            ->sortBy('direction')
            ->values()
            ->toArray();
    }
}
