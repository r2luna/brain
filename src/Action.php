<?php

declare(strict_types=1);

namespace Brain;

use Brain\Actions\Events\Processed;
use Brain\Actions\Events\Processing;
use Brain\Actions\Middleware\FinalizeActionMiddleware;
use Brain\Attributes\OnQueue;
use Brain\Attributes\Sensitive;
use Brain\Exceptions\InvalidPayload;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;

/**
 * Class Action
 *
 * Action class to be used as a base for all actions.
 *
 * @property bool $cancelWorkflow
 */
abstract class Action
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<class-string, string[]> */
    private static array $sensitiveKeysCache = [];

    /**
     * @throws Exception
     */
    public function __construct(
        public array|object|null $payload = null
    ) {
        $startTime = microtime(true);

        $this->standardizePayload();
        $this->validate();
        $this->wrapSensitiveKeys();

        $this->fireEvent(Processing::class, [
            'microtime' => $startTime,
        ]);

        if ($runIn = $this->runIn()) {
            $this->delay($runIn);
        }

        $onQueue = (new ReflectionClass(static::class))
            ->getAttributes(OnQueue::class);

        if ($onQueue !== []) {
            $this->onQueue($onQueue[0]->newInstance()->queue);
        }
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
    public function __get(string $property): mixed
    {
        $value = data_get((array) $this->payload, $property);

        return $value instanceof SensitiveValue ? $value->value() : $value;
    }

    /**
     * Set the payload property magically
     */
    public function __set(string $property, mixed $value): void
    {
        if (! $value instanceof SensitiveValue && in_array($property, static::getSensitiveKeys(), true)) {
            $value = new SensitiveValue($value);
        }

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

        if ($runIfMethod
            && $runIfMethod->getDeclaringClass()->getName() === $reflectionClass->getName()
            && ! $instance->runIf()) {
            return null;
        }

        if ($reflectionClass->hasMethod('newPendingDispatch')) {
            return static::newPendingDispatch($instance);
        }

        return new PendingDispatch($instance); // @codeCoverageIgnore
    }

    /** Returns the list of sensitive keys declared via the #[Sensitive] attribute, merged with workflow-level keys. */
    public static function getSensitiveKeys(): array
    {
        $actionKeys = self::$sensitiveKeysCache[static::class] ??= (function (): array {
            $attributes = (new ReflectionClass(static::class))
                ->getAttributes(Sensitive::class);

            return $attributes !== [] ? $attributes[0]->newInstance()->keys : [];
        })();

        $workflowKeys = Context::get('brain.sensitive_keys', []);

        if ($workflowKeys === []) {
            return $actionKeys;
        }

        return array_values(array_unique([...$actionKeys, ...$workflowKeys]));
    }

    /**
     * It will fire an event to tell anyone that
     * the action has been finished to process
     */
    public function finalize(): void
    {
        $endTime = microtime(true);

        $this->fireEvent(Processed::class, [
            'microtime' => $endTime,
        ]);
    }

    /**
     * Convert the action payload to an array filtered by the properties
     * defined in the class docblock (@property-read tags)
     */
    public function toArray(): array
    {
        $expectedKeys = $this->getExpectedPayloadKeys(true);
        $payloadArray = (array) $this->payload;

        if ($expectedKeys === []) {
            return $payloadArray;
        }

        return array_intersect_key($payloadArray, array_flip($expectedKeys));
    }

    /** Return the middleware that should be applied to the action. */
    public function middleware(): array
    {
        return [new FinalizeActionMiddleware];
    }

    /**
     * This method will set when the action needs to
     * run for the first time in the future
     *
     * @return int|Carbon|null Return seconds or carbon instance in future date
     */
    protected function runIn(): int|Carbon|null
    {
        return null;
    }

    /**
     * This method will be called before the action is dispatched by the workflow.
     * You can use any property of the payload to make a decision
     * if the action should be run.
     */
    protected function runIf(): bool
    {
        return true;
    }

    /**
     * Check if the implemented Action has expected payload keys on
     * the class doc-block tags:
     * - Ex.: @ property-read int $userId
     */
    protected function getExpectedPayloadKeys(bool $all = false): array
    {
        $reflectionClass = new ReflectionClass(static::class);
        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock = $reflectionClass->getDocComment();

        if (is_bool($docBlock)) {
            return [];
        }

        $classDocBlock = $docBlockFactory->create($docBlock);

        $map = array_map(
            function (Tag $tag) use ($all): ?string {
                if ($all && ($tag instanceof PropertyRead || $tag instanceof Property)) {
                    return $tag->getVariableName();
                }

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
     * Tell's the workflow to cancel it
     */
    protected function cancelWorkflow(): void
    {
        $this->cancelWorkflow = true;
    }

    /**
     * Override this method to return validation rules for action payload properties.
     * Rules are validated using Laravel's Validator before the handle() method executes.
     * When rules are present, both Validator-based validation and docblock @property-read
     * key-existence checks are performed.
     *
     * @return array<string, array<int, mixed>|string> The validation rules.
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Checks if the payload has the expected
     * payload keys.
     *
     * @throws Exception
     */
    private function validate(): void
    {
        // ----------------------------------------------------------
        // Validate the payload using the rules() method
        // if the method returns any rules

        $rules = $this->rules();
        if (filled($rules)) {
            Validator::make(
                (array) $this->payload,
                $rules
            )->validate();
        }

        // ----------------------------------------------------------
        // Keeps the old behavior of checking if the payload
        // has the expected keys defined in the class doc-block
        // using @property-read tags

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
            throw new InvalidPayload('Action '.static::class.' :: Expected keys: '.implode(', ', $expectedKeys));
        }

    }

    /**
     * Fire Event for the Listeners save all the info
     * in the database, and we track what is happening to
     * each workflow
     */
    private function fireEvent(string $event, array $meta = []): void
    {
        [$workflow, $runWorkflowId] = Context::get('workflow');

        event(new $event(
            static::class,
            $this->payload,
            $workflow,
            $runWorkflowId,
            $meta
        ));
    }

    /** Wraps sensitive payload keys in SensitiveValue for automatic redaction. */
    private function wrapSensitiveKeys(): void
    {
        foreach (static::getSensitiveKeys() as $key) {
            if (isset($this->payload->$key) && ! $this->payload->$key instanceof SensitiveValue) {
                $this->payload->$key = new SensitiveValue($this->payload->$key);
            }
        }
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
