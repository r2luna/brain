<?php

declare(strict_types=1);

namespace Brain;

use Brain\Processes\Events\Error;
use Brain\Processes\Events\Processed;
use Brain\Processes\Events\Processing;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * When running the process we will assign am uuid
     * to each instance, this will help track the
     * related tasks and sub-processes
     */
    private ?string $uuid = null;

    /**
     * Process constructor.
     */
    public function __construct(
        private array|object|null $payload = null
    ) {
        $this->uuid = Str::uuid()->toString();

        Context::push('process', self::class, $this->uuid);
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

        $this->fireEvent(Processing::class);

        $output = $this->chain
            ? $this->runInChain($this->payload)
            : $this->run($this->payload);

        $this->fireEvent(Processed::class);

        return $output;
    }

    /**
     * Chain all tasks in the order that they were added, and
     * use Bus::chain to dispatch them.
     */
    private function runInChain(?object $payload = null): ?object
    {
        Bus::chain($this->getChainedTasks())
            ->dispatch();

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
                $this->logStep($task, $payload);
                $reflectionClass = new ReflectionClass($task);

                $runIfMethod = $reflectionClass->hasMethod('runIf') ? $reflectionClass->getMethod('runIf') : null;

                if ($runIfMethod && ! $runIfMethod->invoke(new $task($payload))) {
                    $this->logStep($task, $payload, 'Task skipped by runIf condition');

                    continue;
                }

                if (
                    isset($payload->cancelProcess)
                    && $payload->cancelProcess
                ) {
                    $this->logStep($task, $payload, 'Process cancelled');

                    break;
                }

                if ($reflectionClass->implementsInterface(ShouldQueue::class)) {
                    $task::dispatch($payload);

                    continue;
                }

                $temp = $task::dispatchSync($payload);
                $payload = $temp instanceof Task ? $temp->payload : $temp;

                // If the task is a Process, we need to remove the cancelProcess key from the payload.
                // Because the cancel process is only valid for the current process.
                // Not for the entire set
                if ($reflectionClass->isSubclassOf(Process::class)) {
                    unset($payload->cancelProcess);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->fireEvent(Error::class, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (is_array($payload)) {
            return (object) $payload;
        }

        return $payload;
    }

    /**
     * Log the step of the process.
     */
    private function logStep(
        mixed $task,
        object|array|null $payload,
        string $message = 'Running task'
    ): void {
        Log::info($message, [
            'task' => $task,
            'payload' => $payload,
        ]);
    }

    /**
     * Fire Event for the Listeners save all the info
     * in the database, and we track what is happening to
     * each process
     */
    private function fireEvent(string $event, array $meta = []): void
    {
        event(new $event(
            self::class,
            $this->uuid,
            $meta
        ));
    }
}
