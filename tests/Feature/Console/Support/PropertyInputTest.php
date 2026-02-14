<?php

declare(strict_types=1);

use Brain\Console\Support\PropertyInput;

describe('isNullable', function (): void {
    it('detects nullable types', function (): void {
        expect(PropertyInput::isNullable('string|null'))->toBeTrue();
        expect(PropertyInput::isNullable('int|null'))->toBeTrue();
        expect(PropertyInput::isNullable('null|string'))->toBeTrue();
    });

    it('detects non-nullable types', function (): void {
        expect(PropertyInput::isNullable('string'))->toBeFalse();
        expect(PropertyInput::isNullable('int'))->toBeFalse();
        expect(PropertyInput::isNullable('bool'))->toBeFalse();
    });
});

describe('baseType', function (): void {
    it('returns the type as-is for non-nullable types', function (): void {
        expect(PropertyInput::baseType('string'))->toBe('string');
        expect(PropertyInput::baseType('int'))->toBe('int');
        expect(PropertyInput::baseType('bool'))->toBe('bool');
        expect(PropertyInput::baseType('float'))->toBe('float');
        expect(PropertyInput::baseType('array'))->toBe('array');
    });

    it('strips null from nullable types', function (): void {
        expect(PropertyInput::baseType('string|null'))->toBe('string');
        expect(PropertyInput::baseType('null|int'))->toBe('int');
        expect(PropertyInput::baseType('float|null'))->toBe('float');
    });

    it('falls back to string for null-only types', function (): void {
        expect(PropertyInput::baseType('null'))->toBe('string');
    });
});

describe('isModelType', function (): void {
    it('returns false for scalar types', function (): void {
        expect(PropertyInput::isModelType('string'))->toBeFalse();
        expect(PropertyInput::isModelType('int'))->toBeFalse();
        expect(PropertyInput::isModelType('bool'))->toBeFalse();
    });

    it('returns false for non-existent classes', function (): void {
        expect(PropertyInput::isModelType('App\\Models\\NonExistent'))->toBeFalse();
    });

    it('returns false for non-model classes', function (): void {
        expect(PropertyInput::isModelType(Brain\Task::class))->toBeFalse();
        expect(PropertyInput::isModelType('stdClass'))->toBeFalse();
    });
});

describe('castValue', function (): void {
    it('casts to int', function (): void {
        expect(PropertyInput::castValue('42', 'int', false))->toBe(42);
        expect(PropertyInput::castValue('0', 'int', false))->toBe(0);
    });

    it('casts to float', function (): void {
        expect(PropertyInput::castValue('3.14', 'float', false))->toBe(3.14);
        expect(PropertyInput::castValue('0', 'float', false))->toBe(0.0);
    });

    it('casts to array from JSON', function (): void {
        expect(PropertyInput::castValue('["a","b"]', 'array', false))->toBe(['a', 'b']);
        expect(PropertyInput::castValue('{"key":"value"}', 'array', false))->toBe(['key' => 'value']);
    });

    it('returns string for default type', function (): void {
        expect(PropertyInput::castValue('hello', 'string', false))->toBe('hello');
        expect(PropertyInput::castValue('anything', 'unknown', false))->toBe('anything');
    });

    it('returns null for empty nullable values', function (): void {
        expect(PropertyInput::castValue('', 'string', true))->toBeNull();
        expect(PropertyInput::castValue('', 'int', true))->toBeNull();
        expect(PropertyInput::castValue('', 'float', true))->toBeNull();
        expect(PropertyInput::castValue('', 'array', true))->toBeNull();
    });

    it('does not return null for empty non-nullable values', function (): void {
        expect(PropertyInput::castValue('', 'string', false))->toBe('');
        expect(PropertyInput::castValue('', 'int', false))->toBe(0);
    });
});
