<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Symfony\Component\Console\Input\Input;

class TestInput extends Input
{
    public function __construct(
        private ?array $parameters = [],
    ) {
        parent::__construct();

        $this->parse();
    }

    public function __toString(): string
    {
        return collect($this->parameters)->join(' ');
    }

    public function getFirstArgument(): ?string
    {
        return collect($this->parameters)
            ->filter(fn ($value): bool => ! str($value)->startsWith('--'))
            ->first();
    }

    public function hasParameterOption(string|array $values, bool $onlyParams = false): bool
    {
        return collect($this->parameters)
            ->when($onlyParams, fn ($collection) => $collection->filter(fn ($value) => str($value)->startsWith('--')))
            ->contains($values);
    }

    public function getParameterOption(string|array $values, string|bool|int|float|array|null $default = false, bool $onlyParams = false): mixed
    {
        return collect($this->parameters)
            ->filter(fn ($value, $key) => str($key)->startsWith('--'))
            ->filter(fn ($value): bool => in_array($value, (array) $values))
            ->first();
    }

    public function getArgument(string $name): string|int|bool|array|null
    {
        return $this->parameters[$name] ?? null;
    }

    public function hasArgument(string $name): bool
    {
        return isset($this->parameters[$name]);

    }

    protected function parse(): void
    {
        foreach ($this->parameters as $key => $value) {
            if ($key === '--') {
                return;
            }
            if (str_starts_with($key, '-')) {
                $key = ltrim($key, '-');
                $this->options[$key] = $value;
            } else {
                $this->arguments[$key] = $value;
            }
        }
    }
}
