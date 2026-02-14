<?php

declare(strict_types=1);

namespace Brain;

use JsonSerializable;
use SensitiveParameter;
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

    public function __debugInfo(): array
    {
        return ['value' => self::REDACTED];
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return self::REDACTED;
    }
}
