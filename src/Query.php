<?php

declare(strict_types=1);

namespace Brain;

/**
 * Represents an abstract blueprint for a query class
 * that requires a handle method implementation.
 */
abstract class Query
{
    /**
     * Abstract handle method that must be implemented
     * by the child class. This method is responsible for
     * executing the query logic.
     */
    abstract public function handle(): mixed;

    /**
     * Executes the run method by creating a new instance of the class with the provided arguments
     * and invoking the handle method.
     *
     * @param  mixed  ...$arguments  A variadic parameter accepting one or more arguments.
     * @return mixed The result of the handle method execution.
     */
    public static function run(mixed ...$arguments): mixed
    {
        return (new static(...$arguments))->handle(); // @phpstan-ignore-line
    }
}
