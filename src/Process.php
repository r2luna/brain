<?php

declare(strict_types=1);

namespace Brain;

use Brain\Attributes\OnQueue;
use Brain\Attributes\Sensitive;
use Brain\Processes\Events\Error;
use Brain\Processes\Events\Processed;
use Brain\Processes\Events\Processing;
use Brain\Tasks\Events\Cancelled;
use Brain\Tasks\Events\Error as TasksError;
use Brain\Tasks\Events\Skipped;
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
 * Class Process
 *
 * Run a list of tasks in a specific order.
 */
class Process
{
    use Dispatchable;
    use Queueable;

    /**
     * When running the process we will assign am uuid
     * to each instance, this will help track the
     * related tasks and sub-processes
     */
    public ?string $uuid = null;

    /**
     * When chain is set to true, the tasks will be dispatched as a chain.
     * And it will be always send a queue.
     */
    protected bool $chain = false;

    /**
     * List of all tasks to be executed.
     *
     * @var array <int, string>
     */
    protected array $tasks = [];

    /**
     * The name of the Process
     */
    private string $name;

    /**
     * Process constructor.
     */
    public function __construct(
        private array|object|null $payload = null
    ) {
        $this->uuid = Str::uuid()->toString();

        $this->name = (new ReflectionClass($this))->getName();

        Context::add('process', [$this->name, $this->uuid]);

        $onQueue = (new ReflectionClass(static::class))
            ->getAttributes(OnQueue::class);

        if ($onQueue !== []) {
            $this->onQueue($onQueue[0]->newInstance()->queue);
        }
    }

    /**
     * Be able to access all tasks publicly.
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Add new task to be run, always to the end
     * of the process
     *
     * @return $this
     */
    public function addTask(string $class): self
    {
        $this->tasks[] = $class;

        return $this;
    }

    /**
     * Check if the process is set to be chained.
     */
    public function isChained(): bool
    {
        return $this->chain;
    }

    /**
     * Method that will be called when the process is dispatched.
     *
     * @throws ReflectionException
     * @throws Throwable
     */
    public function handle(): object|array|null
    {
        if (is_array($this->payload)) {
            $this->payload = (object) $this->payload;
        }

        $sensitiveKeys = (new ReflectionClass(static::class))
            ->getAttributes(Sensitive::class);

        if ($sensitiveKeys !== []) {
            Context::add('brain.sensitive_keys', $sensitiveKeys[0]->newInstance()->keys);
        }

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
        }

        return $output;
    }

    /**
     * Chain all tasks in the order that they were added, and
     * use Bus::chain to dispatch them.
     */
    private function runInChain(?object $payload = null): ?object
    {
        $chain = Bus::chain($this->getChainedTasks());

        if (($queue = $this->resolveQueue()) !== null && ($queue = $this->resolveQueue()) !== '' && ($queue = $this->resolveQueue()) !== '0') {
            $chain->onQueue($queue);
        }

        $chain->dispatch();

        return $payload;
    }

    /**
     * Get all tasks as objects to be dispatched in the chain.
     */
    private function getChainedTasks(): array
    {
        return array_map(fn (string $task): object => new $task($this->payload), $this->tasks);
    }

    /**
     * Run all tasks in the order that they were added.
     * Also checks if the task is a "QueueableTask", if so, it will be dispatched as a queue.
     * Otherwise, it will be dispatched synchronously and the return will be the payload
     * sent to the next task.
     *
     * @throws ReflectionException|Throwable
     */
    private function run(array|object|null $payload): ?object
    {
        DB::beginTransaction();

        try {
            foreach ($this->tasks as $task) {
                $reflectionClass = new ReflectionClass($task);

                if ($reflectionClass->hasMethod('runIf')) {
                    $method = $reflectionClass->getMethod('runIf');

                    if ($method->getDeclaringClass()->getName() === $reflectionClass->getName()) {
                        $instance = new $task($payload);

                        if (! $method->invoke($instance)) {
                            event(new Skipped($task, payload: $payload, runProcessId: $this->uuid));

                            continue;
                        }
                    }
                }

                if (
                    isset($payload->cancelProcess)
                    && $payload->cancelProcess
                ) {
                    event(new Cancelled($task, payload: $payload, runProcessId: $this->uuid));

                    break;
                }

                if ($reflectionClass->implementsInterface(ShouldQueue::class)) {
                    $processQueue = $this->resolveQueue();
                    $instance = new $task($payload);

                    if ($instance->queue === null && $processQueue !== null) {
                        $instance->onQueue($processQueue);
                    }

                    dispatch($instance);

                    continue;
                }

                try {
                    $temp = $task::dispatchSync($payload);
                    if ($temp instanceof Task) {
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

                    event(new TasksError($task, payload: $payload, runProcessId: $this->uuid, meta: $meta));

                    throw $e;
                }

                // If the task is a Process, we need to remove the cancelProcess key from the payload.
                // Because the cancel process is only valid for the current process.
                // Not for the entire set
                if ($reflectionClass->isSubclassOf(Process::class)) {
                    unset($payload->cancelProcess);
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
     * Resolve the queue name from the #[OnQueue] attribute on this Process class.
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
     * each process
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
