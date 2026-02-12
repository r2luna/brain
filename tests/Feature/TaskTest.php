<?php

declare(strict_types=1);

use Brain\Exceptions\InvalidPayload;
use Brain\Task;
use Brain\Tasks\Events\Processed;
use Brain\Tasks\Middleware\FinalizeTaskMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

function getJobFromReflection(PendingDispatch $task): object
{
    $reflection = new ReflectionClass($task);
    $property = $reflection->getProperty('job');

    return $property->getValue($task);
}

test('make sure that it is using the correct traits', function (): void {
    $expectedTraits = [
        Dispatchable::class,
        InteractsWithQueue::class,
        Queueable::class,
        SerializesModels::class,
    ];

    $actualTraits = class_uses(Task::class);

    expect($actualTraits)->toHaveKeys($expectedTraits);
});

it('should validate the payload of a Task based on the docblock of the class', function (): void {
    /** @property-read string $name */
    class TempTask extends Task {}

    TempTask::dispatch();
})->throws(InvalidPayload::class);

it('should return void if there is nothing to validate', function (): void {
    class ValidateVoidTask extends Task {}

    ValidateVoidTask::dispatch();
})->throwsNoExceptions();

it('should make sure that we standardize the payload in an object', function (): void {
    /** @property-read string $name */
    class ArrayTask extends Task {}
    $task = ArrayTask::dispatch(['name' => 'John Doe']);
    $job = getJobFromReflection($task);

    expect($job->payload)->toBeObject();

    class NullTask extends Task {}
    $task = NullTask::dispatch();
    expect($job->payload)->toBeObject();

    /** @property-read string $name */
    class ObjectTask extends Task {}
    $task = ObjectTask::dispatch((object) ['name' => 'John Doe']);
    expect($job->payload)->toBeObject();
});

it('should delay the task if the runIn method is set', function (): void {
    class DelayTask extends Task
    {
        public function runIn(): int
        {
            return 10;
        }
    }

    $task = DelayTask::dispatch();
    $job = getJobFromReflection($task);
    expect($job->delay)->toBe(10);
});

it('s possible to return int or a Carbon instance', function (): void {
    class DelayIntTask extends Task
    {
        public function runIn(): int
        {
            return 10;
        }
    }

    $task = DelayIntTask::dispatch();

    $job = getJobFromReflection($task);

    expect($job->delay)->toBe(10);

    Carbon::setTestNow('2021-01-01 01:00:00');

    class DelayCarbonTask extends Task
    {
        public function runIn(): Carbon
        {
            return now()->addSeconds(10);
        }
    }

    $task = DelayCarbonTask::dispatch();
    $job = getJobFromReflection($task);
    expect($job->delay)->toBeInstanceOf(Carbon::class);
    expect($job->delay)->format('Y-m-d h:i:s')->toBe('2021-01-01 01:00:10');
});

test('if runIn is not set delay should be null', function (): void {
    class NoDelayTask extends Task {}

    $task = NoDelayTask::dispatch();
    $job = getJobFromReflection($task);
    expect($job->delay)->toBeNull();
});

it('should add cancelProcess to the payload when cancelProcess method is called', function (): void {
    class CancelTask extends Task
    {
        public function handle(): self
        {
            $this->cancelProcess();

            return $this;
        }
    }

    $task = CancelTask::dispatchSync();

    expect($task->payload)->toHaveKey('cancelProcess');
    expect($task->payload->cancelProcess)->toBeTrue();
});

test('from inside a task we should be able to access payload data magically using __get method', function (): void {
    /** @property-read string $name */
    class MagicTask extends Task
    {
        public function handle(): self
        {
            expect($this->name)->toBe('John Doe');

            return $this;
        }
    }

    MagicTask::dispatch(['name' => 'John Doe']);
});

it('should be able to set any property in the payload magically using __set method', function (): void {
    /** @property string $name */
    class Magic2Task extends Task
    {
        public function handle(): self
        {
            $this->name = 'John Doe';

            return $this;
        }
    }

    $task = Magic2Task::dispatchSync();

    expect($task->name)->toBe('John Doe');
});

it('should be possible to have a conditional run inside a process', function (): void {
    /**
     * Class ConditionalTask
     *
     * @property-read int $id
     */
    class ConditionalTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }

        protected function runIf(): bool
        {
            return $this->id > 2;
        }
    }

    class ConditionalProcess extends Brain\Process
    {
        protected array $tasks = [
            ConditionalTask::class,
        ];
    }

    Bus::fake([
        ConditionalTask::class,
    ]);

    ConditionalProcess::dispatch(['id' => 1]);

    Bus::assertNotDispatched(ConditionalTask::class);
});

it('should be able to conditionally run outside a process', function (): void {
    Bus::fake([Temp2Task::class]);

    class Temp2Task extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    /**
     * Class ConditionalOutTask
     *
     * @property-read int $id
     */
    class ConditionalOutTask extends Task
    {
        public function handle(): self
        {
            Temp2Task::dispatch();

            return $this;
        }

        protected function runIf(): bool
        {
            return false;
        }
    }

    ConditionalOutTask::dispatch(['id' => 1]);

    Bus::assertNotDispatched(Temp2Task::class);
});

it('should return true by default when runIf is called directly', function (): void {
    class DefaultRunIfTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = new DefaultRunIfTask;
    $reflection = new ReflectionClass($task);
    $method = $reflection->getMethod('runIf');

    $result = $method->invoke($task);

    expect($result)->toBeTrue();
});

it('should return filtered array based on docblock properties using toArray method', function (): void {
    /**
     * @property-read string $name
     * @property-read int $age
     */
    class FilteredTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = FilteredTask::dispatchSync([
        'name' => 'John Doe',
        'age' => 30,
        'email' => 'john@example.com', // This should be filtered out
        'phone' => '123-456-7890', // This should be filtered out
    ]);

    $result = $task->toArray();

    expect($result)->toHaveKeys(['name', 'age']);
    expect($result)->not->toHaveKeys(['email', 'phone']);
    expect($result['name'])->toBe('John Doe');
    expect($result['age'])->toBe(30);
});

it('should return all payload when no docblock properties are defined using toArray method', function (): void {
    class NoDocBlockTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = NoDocBlockTask::dispatchSync([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '123-456-7890',
    ]);

    $result = $task->toArray();

    expect($result)->toHaveKeys(['name', 'email', 'phone']);
    expect($result['name'])->toBe('John Doe');
    expect($result['email'])->toBe('john@example.com');
    expect($result['phone'])->toBe('123-456-7890');
});

it('should return empty array when payload is empty using toArray method', function (): void {
    class EmptyPayloadTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = EmptyPayloadTask::dispatchSync([]);

    $result = $task->toArray();

    expect($result)->toBeEmpty();
});

it('should handle mixed payload types when using toArray method', function (): void {
    /**
     * @property-read string $name
     * @property-read bool $active
     * @property-read array $tags
     */
    class MixedTypeTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = MixedTypeTask::dispatchSync([
        'name' => 'John Doe',
        'active' => true,
        'tags' => ['php', 'laravel'],
        'unwanted' => 'should be filtered',
    ]);

    $result = $task->toArray();

    expect($result)->toHaveKeys(['name', 'active', 'tags']);
    expect($result)->not->toHaveKey('unwanted');
    expect($result['name'])->toBe('John Doe');
    expect($result['active'])->toBeTrue();
    expect($result['tags'])->toBe(['php', 'laravel']);
});

it('should be able to pass rules to the task to be validated using Validator facade', function (): void {
    /**
     * @property-read string $name
     * @property int $age
     */
    class RulesTask extends Task
    {
        public function rules(): array
        {
            return [
                'name' => ['required'],
                'age' => ['required', 'integer', 'min:18'],
            ];
        }

        public function handle(): self
        {
            return $this;
        }
    }

    expect(
        fn () => RulesTask::dispatchSync([
            'name' => 'John Doe',
            'age' => 30,
        ])
    )->not->toThrow(Illuminate\Validation\ValidationException::class);

    expect(
        fn () => RulesTask::dispatchSync([])
    )->toThrow(
        Illuminate\Validation\ValidationException::class,
        __('validation.required', ['attribute' => 'name']),
    );

    expect(
        fn () => RulesTask::dispatchSync([
            'name' => 'John Doe',
        ])
    )->toThrow(
        Illuminate\Validation\ValidationException::class,
        __('validation.required', ['attribute' => 'age']),
    );
});

it('returns middleware array containing FinalizeTaskMiddleware', function (): void {
    class MiddlewareTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = MiddlewareTask::dispatchSync();
    $middlewares = $task->middleware();

    expect($middlewares)->toBeArray()
        ->and($middlewares)->toHaveCount(1)
        ->and($middlewares[0])->toBeInstanceOf(FinalizeTaskMiddleware::class);
});

it('fires Processed event when finalize is called', function (): void {
    Event::fake();

    class FinalizeDirectTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = FinalizeDirectTask::dispatchSync();

    $task->finalize();

    Event::assertDispatched(Processed::class);
});

it('FinalizeTaskMiddleware triggers finalize and dispatches Processed event', function (): void {
    Event::fake();

    class FinalizeMiddlewareTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $task = FinalizeMiddlewareTask::dispatchSync();

    $middleware = new FinalizeTaskMiddleware;

    $middleware->handle($task, fn ($t) => $t);

    Event::assertDispatched(Processed::class);
});

it('process calls finalize on task instances and fires Processed event', function (): void {
    Event::fake();

    class ProcessFinalizeTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    class ProcessWithFinalize extends Brain\Process
    {
        protected array $tasks = [
            ProcessFinalizeTask::class,
        ];
    }

    $process = new ProcessWithFinalize(null);
    $process->handle();

    Event::assertDispatched(Processed::class);
});

it('queued tasks are finalized before going through next middleware', function (): void {
    Event::fake();

    class QueuedTask extends Task implements ShouldQueue
    {
        public function handle(): self
        {
            return $this;
        }
    }

    class ProcessQueuedTask extends Brain\Process
    {
        protected array $tasks = [
            QueuedTask::class,
        ];
    }

    ProcessQueuedTask::dispatchSync();

    Event::assertDispatched(Processed::class);
});
