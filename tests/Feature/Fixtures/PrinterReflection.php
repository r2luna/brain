<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Brain\Console\Printer;
use ReflectionClass;

class PrinterReflection
{
    public ReflectionClass $reflection;

    public function __construct(protected Printer $printer)
    {
        $this->reflection = new ReflectionClass($this->printer);
    }

    public function run(string $method, array $arguments = []): mixed
    {
        $method = $this->reflection->getMethod($method);

        return $method->invokeArgs($this->printer, $arguments);
    }

    public function get(string $property): mixed
    {
        $property = $this->reflection->getProperty($property);

        return $property->getValue($this->printer);
    }

    public function set(string $property, mixed $value): void
    {
        $property = $this->reflection->getProperty($property);

        $property->setValue($this->printer, $value);
    }
}
