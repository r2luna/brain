<?php

declare(strict_types=1);

namespace Brain;

use Brain\Exceptions\InvalidPayload;
use Brain\Tasks\Events\Processing;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

/**
 * Class Task: Task class to be used as a
 * base for all tasks.
 *
 * @property-read bool $cancelProcess
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

        if (method_exists($this, 'runIn')) {
            $this->delay($this->runIn());
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
        $this->payload = (object) array_merge(
            (array) $this->payload,
            ['cancelProcess' => true]
        );
    }

    /**
     * Checks if the payload has the expected
     * payload keys.
     *
     * @throws Exception
     */
    private function validate(): void
    {
        if (! $this->payload) {
            return;
        }

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
