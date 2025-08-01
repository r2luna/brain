<?php

declare(strict_types=1);

namespace Brain;

use Brain\Exceptions\InvalidPayload;
use Brain\Tasks\Events\Processing;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;

/**
 * Class Task
 *
 * Task class to be used as a base for all tasks.
 *
 * @property bool $cancelProcess
 */
abstract class Task
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @throws Exception
     */
    public function __construct(
        public array|object|null $payload = null
    ) {
        $this->standardizePayload();

        $this->fireEvent(Processing::class);

        $this->validate();

        if ($runIn = $this->runIn()) {
            $this->delay($runIn);
        }
    }

    /**
     * It will fire an event to tell anyone that
     * the task has been finished to process
     */
    public function __destruct()
    {
        // TODO: needs to understand why the event() is not being fired here
        //        $this->fireEvent(Processed::class);
    }

    /**
     * For some reason, when I'm testing the class the
     * __invoke method is not being called, so I'm
     * calling it here to make sure that the method
     * exists to be called. Need to investigate why
     * this is happening.
     */
    public function __invoke(): void {}

    /**
     * Gets the payload property magically,
     * assuming that the payload has the property
     * or return null.
     *
     * @return mixed|null
     */
    public function __get(string $property)
    {
        $tmpArray = (array) $this->payload;

        return data_get($tmpArray, $property);
    }

    /**
     * Set the payload property magically
     */
    public function __set(string $property, mixed $value): void
    {
        $this->payload->$property = $value;
    }

    /**
     * Dispatch the job with the given arguments.
     *
     * @param  mixed  ...$arguments
     *
     * @throws ReflectionException
     */
    public static function dispatch(...$arguments)
    {
        $reflectionClass = new ReflectionClass(static::class);
        $runIfMethod = $reflectionClass->hasMethod('runIf') ? $reflectionClass->getMethod('runIf') : null;

        // @phpstan-ignore-next-line
        $instance = new static(...$arguments);

        if ($runIfMethod && ! $runIfMethod->invoke($instance)) {
            return null;
        }

        if ($reflectionClass->hasMethod('newPendingDispatch')) {
            return static::newPendingDispatch($instance);
        }

        return new PendingDispatch($instance); // @codeCoverageIgnore
    }

    /**
     * This method will set when the task needs to
     * run for the first time in the future
     *
     * @return int|Carbon|null Return seconds or carbon instance in future date
     */
    protected function runIn(): int|Carbon|null
    {
        return null;
    }

    /**
     * This method will be called before the task is dispatched by the process.
     * You can use any property of the payload to make a decision
     * if the task should be run.
     */
    protected function runIf(): bool
    {
        return true;
    }

    /**
     * Check if the implemented Task has expected payload keys on
     * the class doc-block tags:
     * - Ex.: @ property-read int $userId
     */
    protected function getExpectedPayloadKeys(): array
    {
        $reflectionClass = new ReflectionClass(static::class);
        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock = $reflectionClass->getDocComment();

        if (is_bool($docBlock)) {
            return [];
        }

        $classDocBlock = $docBlockFactory->create($docBlock);

        $map = array_map(
            function (Tag $tag): ?string {
                if ($tag instanceof PropertyRead) {
                    return $tag->getVariableName();
                }

                return null;
            },
            $classDocBlock->getTags()
        );

        // Clear the expected keys removing all null values
        // null value means that the tag is not a @property-read
        return array_filter($map, fn (?string $item): bool => ! is_null($item));
    }

    /**
     * Tell's the process to cancel it
     */
    protected function cancelProcess(): void
    {
        $this->cancelProcess = true;
    }

    /**
     * Checks if the payload has the expected
     * payload keys.
     *
     * @throws Exception
     */
    private function validate(): void
    {
        $expectedKeys = $this->getExpectedPayloadKeys();

        if ($expectedKeys === []) {
            return;
        }

        if (
            array_intersect(
                array_keys((array) $this->payload),
                $expectedKeys,
            ) === []
        ) {
            throw new InvalidPayload('Task '.static::class.' :: Expected keys: '.implode(', ', $expectedKeys));
        }
    }

    /**
     * Fire Event for the Listeners save all the info
     * in the database, and we track what is happening to
     * each process
     */
    private function fireEvent(string $event, array $meta = []): void
    {
        [$process, $runProcessId] = Context::get('process');

        event(new $event(
            static::class,
            $this->payload,
            $process,
            $runProcessId,
            $meta
        ));
    }

    /**
     * Standardizes the payload by ensuring it is always converted to an object.
     * If the payload is null, it initializes it as an empty array and converts it into an object.
     * If the payload is an array, it directly converts it into an object.
     */
    private function standardizePayload(): void
    {
        if (is_null($this->payload)) {
            $this->payload = [];
        }

        if (is_array($this->payload)) {
            $this->payload = (object) $this->payload;
        }
    }
}
