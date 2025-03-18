<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Support\Facades\File;
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
            ->map(fn ($value, $key) => [
                'domain' => $key,
                'path' => $value,
                'processes' => $this->loadProcessesFor($value),
            ])
            ->toArray();

        $this->domains = $domains;
    }

    private function loadProcessesFor(string $domainPath): array
    {
        $path = $domainPath.DIRECTORY_SEPARATOR.'Processes';

        return collect(File::files($path))
            ->map(function ($value) use ($domainPath): array {
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
}
