<?php

declare(strict_types=1);

namespace Brain\Attributes;

use Attribute;
use Brain\Process;
use Brain\Workflow;
use ReflectionClass;

#[Attribute(Attribute::TARGET_CLASS)]
class Sensitive
{
    public array $keys;

    public function __construct(string ...$keys)
    {
        $this->keys = $keys;
    }

    /**
     * Returns the sensitive keys declared on the class and its parents.
     *
     * @return string[]
     */
    public static function keysFor(string $class): array
    {
        $keys = [];

        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes(self::class) as $attribute) {
                $keys = [...$keys, ...$attribute->newInstance()->keys];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Returns the sensitive keys of every class, including the default
     * actions/tasks of nested workflows and processes.
     *
     * @return string[]
     */
    public static function keysForAll(array $classes): array
    {
        $keys = [];

        foreach ($classes as $class) {
            $keys = [...$keys, ...self::keysFor($class)];

            $children = match (true) {
                is_subclass_of($class, Workflow::class) => 'actions',
                is_subclass_of($class, Process::class) => 'tasks',
                default => null,
            };

            if ($children !== null) {
                $defaults = (new ReflectionClass($class))->getDefaultProperties();
                $keys = [...$keys, ...self::keysForAll($defaults[$children] ?? [])];
            }
        }

        return array_values(array_unique($keys));
    }
}
