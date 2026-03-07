<?php

declare(strict_types=1);

namespace Brain;

use Brain\Actions\Events\Cancelled;
use Brain\Actions\Events\Error as ActionsError;
use Brain\Actions\Events\Skipped;
use Brain\Attributes\OnQueue;
use Brain\Attributes\Sensitive;
use Brain\Workflows\Events\Error;
use Brain\Workflows\Events\Processed;
use Brain\Workflows\Events\Processing;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Class Workflow
 *
 * Run a list of actions in a specific order.
 */
class Workflow
{
    use Dispatchable;
    use Queueable;

    /**
     * When running the workflow we will assign a uuid
     * to each instance, this will help track the
     * related actions and sub-workflows
     */
    public ?string $uuid = null;

    /**
     * When chain is set to true, the actions will be dispatched as a chain.
     * And it will be always send a queue.
     */
    protected bool $chain = false;

    /**
     * List of all actions to be executed.
     *
     * @var array <int, string>
     */
    protected array $actions = [];

    /**
     * The name of the Workflow
     */
    private string $name;

    /**
     * Workflow constructor.
     */
    public function __construct(
        private array|object|null $payload = null
    ) {
        $this->uuid = Str::uuid()->toString();

        $this->name = (new ReflectionClass($this))->getName();

        Context::add('workflow', [$this->name, $this->uuid]);

        $onQueue = (new ReflectionClass(static::class))
            ->getAttributes(OnQueue::class);

        if ($onQueue !== []) {
            $this->onQueue($onQueue[0]->newInstance()->queue);
        }
    }

    /**
     * Be able to access all actions publicly.
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Add new action to be run, always to the end
     * of the workflow
     *
     * @return $this
     */
    public function addAction(string $class): self
    {
        $this->actions[] = $class;

        return $this;
    }

    /**
     * Check if the workflow is set to be chained.
     */
    public function isChained(): bool
    {
        return $this->chain;
    }

    /**
     * Method that will be called when the workflow is dispatched.
     *
     * @throws ReflectionException
     * @throws Throwable
     */
    public function handle(): object|array|null
    {
        if (is_array($this->payload)) {
            $this->payload = (object) $this->payload;
        }

        $previousKeys = Context::get('brain.sensitive_keys', []);

        $sensitiveAttr = (new ReflectionClass(static::class))
            ->getAttributes(Sensitive::class);

        Context::add('brain.sensitive_keys', $sensitiveAttr !== []
            ? $sensitiveAttr[0]->newInstance()->keys
            : []);

        $this->fireEvent(Processing::class, [
            'timestamp' => microtime(true),
        ]);

        try {
            $output = $this->chain
                ? $this->runInChain($this->payload)
                : $this->run($this->payload);

            $this->fireEvent(Processed::class, [
                'timestamp' => microtime(true),
            ]);
        } catch (Exception $e) {
            $this->fireEvent(Error::class, [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            throw $e;
        } finally {
            Context::forget('brain.sensitive_keys');

            if ($previousKeys !== []) {
                Context::add('brain.sensitive_keys', $previousKeys);
            }
        }

        return $output;
    }

    /**
     * Chain all actions in the order that they were added, and
     * use Bus::chain to dispatch them.
     */
    private function runInChain(?object $payload = null): ?object
    {
        $chain = Bus::chain($this->getChainedActions());

        if (! in_array($queue = $this->resolveQueue(), [null, '', '0'], true)) {
            $chain->onQueue($queue);
        }

        $chain->dispatch();

        return $payload;
    }

    /**
     * Get all actions as objects to be dispatched in the chain.
     */
    private function getChainedActions(): array
    {
        return array_map(fn (string $action): object => new $action($this->payload), $this->actions);
    }

    /**
     * Run all actions in the order that they were added.
     * Also checks if the action is a "QueueableAction", if so, it will be dispatched as a queue.
     * Otherwise, it will be dispatched synchronously and the return will be the payload
     * sent to the next action.
     *
     * @throws ReflectionException|Throwable
     */
    private function run(array|object|null $payload): ?object
    {
        DB::beginTransaction();

        try {
            foreach ($this->actions as $action) {
                $reflectionClass = new ReflectionClass($action);

                if ($reflectionClass->hasMethod('runIf')) {
                    $method = $reflectionClass->getMethod('runIf');

                    if ($method->getDeclaringClass()->getName() === $reflectionClass->getName()) {
                        $instance = new $action($payload);

                        if (! $method->invoke($instance)) {
                            event(new Skipped($action, payload: $payload, runWorkflowId: $this->uuid));

                            continue;
                        }
                    }
                }

                if (
                    isset($payload->cancelWorkflow)
                    && $payload->cancelWorkflow
                ) {
                    event(new Cancelled($action, payload: $payload, runWorkflowId: $this->uuid));

                    break;
                }

                if ($reflectionClass->implementsInterface(ShouldQueue::class)) {
                    $workflowQueue = $this->resolveQueue();
                    $instance = new $action($payload);

                    if ($instance->queue === null && $workflowQueue !== null) {
                        $instance->onQueue($workflowQueue);
                    }

                    dispatch($instance);

                    continue;
                }

                try {
                    $temp = $action::dispatchSync($payload);
                    if ($temp instanceof Action) {
                        // finalize() will be no-op if middleware already finalized
                        $temp->finalize();
                        $payload = $temp->payload;
                    } else {
                        $payload = $temp;
                    }
                } catch (Throwable $e) {
                    $meta = [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ];

                    event(new ActionsError($action, payload: $payload, runWorkflowId: $this->uuid, meta: $meta));

                    throw $e;
                }

                // If the action is a Workflow, we need to remove the cancelWorkflow key from the payload.
                // Because the cancel workflow is only valid for the current workflow.
                // Not for the entire set
                if ($reflectionClass->isSubclassOf(Workflow::class)) {
                    unset($payload->cancelWorkflow);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if (is_array($payload)) {
            return (object) $payload;
        }

        return $payload;
    }

    /**
     * Resolve the queue name from the #[OnQueue] attribute on this Workflow class.
     */
    private function resolveQueue(): ?string
    {
        $attributes = (new ReflectionClass(static::class))
            ->getAttributes(OnQueue::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance()->queue;
    }

    /**
     * Fire Event for the Listeners save all the info
     * in the database, and we track what is happening to
     * each workflow
     */
    private function fireEvent(string $event, array $meta = []): void
    {
        event(new $event(
            $this->name,
            $this->uuid,
            [],
            $meta
        ));
    }
}
