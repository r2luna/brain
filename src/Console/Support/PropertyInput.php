<?php

declare(strict_types=1);

namespace Brain\Console\Support;

use Illuminate\Database\Eloquent\Model;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

/** Type-aware input helper for prompting Brain property values. */
class PropertyInput
{
    /**
     * Prompt the user for a property value based on its type metadata.
     *
     * @param  array{name: string, type: string, direction: string, sensitive: bool}  $property
     */
    public static function prompt(array $property): mixed
    {
        $name = $property['name'];
        $type = $property['type'];
        $nullable = self::isNullable($type);
        $baseType = self::baseType($type);

        if (self::isModelType($baseType)) {
            return self::promptModel($name, $baseType, $nullable); // @codeCoverageIgnore
        }

        if ($baseType === 'bool') {
            return confirm(label: $name, default: false);
        }

        $label = $baseType === 'array' ? "{$name} (JSON)" : $name;
        $value = text(label: $label, required: ! $nullable);

        return self::castValue($value, $baseType, $nullable);
    }

    /** Check if a type string is nullable. */
    public static function isNullable(string $type): bool
    {
        return str_contains($type, 'null');
    }

    /** Extract the base type, stripping nullable markers. */
    public static function baseType(string $type): string
    {
        $parts = array_map('trim', explode('|', $type));
        $filtered = array_filter($parts, fn (string $part): bool => $part !== 'null');

        return $filtered !== [] ? reset($filtered) : 'string';
    }

    /** Determine if a type string represents an Eloquent model. */
    public static function isModelType(string $type): bool
    {
        return class_exists($type) && is_subclass_of($type, Model::class);
    }

    /** Cast a raw string value to the appropriate PHP type. */
    public static function castValue(string $value, string $type, bool $nullable): mixed
    {
        if ($value === '' && $nullable) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'array' => json_decode($value, true),
            default => $value,
        };
    }

    /** @codeCoverageIgnore */
    private static function promptModel(string $name, string $modelClass, bool $nullable): ?int
    {
        $displayColumn = self::guessDisplayColumn($modelClass);

        $id = search(
            label: "{$name} ({$modelClass})",
            options: function (string $query) use ($modelClass, $displayColumn): array {
                if ($query === '') {
                    return [];
                }

                return $modelClass::query()
                    ->where($displayColumn, 'like', "%{$query}%")
                    ->limit(10)
                    ->pluck($displayColumn, 'id')
                    ->all();
            },
            required: ! $nullable,
        );

        return $id === '' || $id === null ? null : (int) $id;
    }

    /** @codeCoverageIgnore */
    private static function guessDisplayColumn(string $modelClass): string
    {
        $instance = new $modelClass;
        $columns = ['name', 'title', 'email'];

        foreach ($columns as $column) {
            if ($instance->getConnection()->getSchemaBuilder()->hasColumn($instance->getTable(), $column)) {
                return $column;
            }
        }

        return $instance->getKeyName();
    }
}
