<?php

declare(strict_types=1);

namespace Brain;

use Illuminate\Support\Facades\Crypt;
use JsonSerializable;
use SensitiveParameter;
use stdClass;
use Stringable;

class SensitiveValue implements JsonSerializable, Stringable
{
    private const string REDACTED = '**********';

    public function __construct(
        #[SensitiveParameter] private readonly mixed $value
    ) {}

    public function __toString(): string
    {
        return self::REDACTED;
    }

    /**
     * The mangled key overrides the private property in VarDumper output.
     */
    public function __debugInfo(): array
    {
        return ["\0".self::class."\0value" => self::REDACTED];
    }

    /**
     * Encrypts the value so queued jobs never store it in plain text.
     */
    public function __serialize(): array
    {
        return ['value' => Crypt::encrypt($this->value)];
    }

    public function __unserialize(array $data): void
    {
        $this->value = Crypt::decrypt($data['value']);
    }

    /**
     * Wraps the given keys of the payload, supporting dot notation.
     */
    public static function wrap(object $payload, array $keys): object
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if ($value !== null && ! $value instanceof self) {
                data_set($payload, $key, new self($value));
            }
        }

        return $payload;
    }

    /**
     * Returns a copy of the value with every SensitiveValue replaced by its real value.
     */
    public static function unwrap(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->value;
        }

        if (is_array($value)) {
            return array_map(self::unwrap(...), $value);
        }

        if ($value instanceof stdClass) {
            return (object) self::unwrap((array) $value);
        }

        return $value;
    }

    /**
     * Replaces every sensitive value found in the payload inside the message.
     */
    public static function redact(string $message, mixed $payload): string
    {
        $secrets = array_unique(self::secretsFrom($payload));
        $secrets = array_filter($secrets, fn (string $secret): bool => $secret !== '');

        usort($secrets, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($secrets, self::REDACTED, $message);
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return self::REDACTED;
    }

    /**
     * Collects the scalar values held by SensitiveValue instances.
     *
     * @return string[]
     */
    private static function secretsFrom(mixed $value, bool $sensitive = false): array
    {
        if ($value instanceof self) {
            return self::secretsFrom($value->value, true);
        }

        if (is_array($value) || $value instanceof stdClass) {
            $secrets = [];

            foreach ((array) $value as $item) {
                $secrets = [...$secrets, ...self::secretsFrom($item, $sensitive)];
            }

            return $secrets;
        }

        if ($sensitive && (is_string($value) || is_int($value) || is_float($value))) {
            return [(string) $value];
        }

        return [];
    }
}
