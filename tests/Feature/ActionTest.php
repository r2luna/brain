<?php

declare(strict_types=1);

use Brain\Action;
use Brain\Actions\Events\Processed;
use Brain\Actions\Middleware\FinalizeActionMiddleware;
use Brain\Exceptions\InvalidPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Fixtures\OnQueueAction;

function getActionFromReflection(PendingDispatch $action): object
{
    $reflection = new ReflectionClass($action);
    $property = $reflection->getProperty('job');

    return $property->getValue($action);
}

test('make sure that it is using the correct traits', function (): void {
    $expectedTraits = [
        Dispatchable::class,
        InteractsWithQueue::class,
        Queueable::class,
        SerializesModels::class,
    ];

    $actualTraits = class_uses(Action::class);

    expect($actualTraits)->toHaveKeys($expectedTraits);
});

it('should validate the payload of an Action based on the docblock of the class', function (): void {
    /** @property-read string $name */
    class TempAction extends Action {}

    TempAction::dispatch();
})->throws(InvalidPayload::class);

it('should return void if there is nothing to validate', function (): void {
    class ValidateVoidAction extends Action {}

    ValidateVoidAction::dispatch();
})->throwsNoExceptions();

it('should make sure that we standardize the payload in an object', function (): void {
    /** @property-read string $name */
    class ArrayAction extends Action {}
    $action = ArrayAction::dispatch(['name' => 'John Doe']);
    $job = getActionFromReflection($action);

    expect($job->payload)->toBeObject();

    class NullAction extends Action {}
    $action = NullAction::dispatch();
    expect($job->payload)->toBeObject();

    /** @property-read string $name */
    class ObjectAction extends Action {}
    $action = ObjectAction::dispatch((object) ['name' => 'John Doe']);
    expect($job->payload)->toBeObject();
});

it('should delay the action if the runIn method is set', function (): void {
    class DelayAction extends Action
    {
        public function runIn(): int
        {
            return 10;
        }
    }

    $action = DelayAction::dispatch();
    $job = getActionFromReflection($action);
    expect($job->delay)->toBe(10);
});

it('s possible to return int or a Carbon instance for action delay', function (): void {
    class DelayIntAction extends Action
    {
        public function runIn(): int
        {
            return 10;
        }
    }

    $action = DelayIntAction::dispatch();

    $job = getActionFromReflection($action);

    expect($job->delay)->toBe(10);

    Carbon::setTestNow('2021-01-01 01:00:00');

    class DelayCarbonAction extends Action
    {
        public function runIn(): Carbon
        {
            return now()->addSeconds(10);
        }
    }

    $action = DelayCarbonAction::dispatch();
    $job = getActionFromReflection($action);
    expect($job->delay)->toBeInstanceOf(Carbon::class);
    expect($job->delay)->format('Y-m-d h:i:s')->toBe('2021-01-01 01:00:10');
});

test('if runIn is not set delay should be null for action', function (): void {
    class NoDelayAction extends Action {}

    $action = NoDelayAction::dispatch();
    $job = getActionFromReflection($action);
    expect($job->delay)->toBeNull();
});

it('should add cancelWorkflow to the payload when cancelWorkflow method is called', function (): void {
    class CancelAction extends Action
    {
        public function handle(): self
        {
            $this->cancelWorkflow();

            return $this;
        }
    }

    $action = CancelAction::dispatchSync();

    expect($action->payload)->toHaveKey('cancelWorkflow');
    expect($action->payload->cancelWorkflow)->toBeTrue();
});

test('from inside an action we should be able to access payload data magically using __get method', function (): void {
    /** @property-read string $name */
    class MagicAction extends Action
    {
        public function handle(): self
        {
            expect($this->name)->toBe('John Doe');

            return $this;
        }
    }

    MagicAction::dispatch(['name' => 'John Doe']);
});

it('should be able to set any property in the payload magically using __set method', function (): void {
    /** @property string $name */
    class Magic2Action extends Action
    {
        public function handle(): self
        {
            $this->name = 'John Doe';

            return $this;
        }
    }

    $action = Magic2Action::dispatchSync();

    expect($action->name)->toBe('John Doe');
});

it('should be possible to have a conditional run inside a workflow', function (): void {
    /**
     * Class ConditionalAction
     *
     * @property-read int $id
     */
    class ConditionalAction extends Action
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

    class ConditionalWorkflow extends Brain\Workflow
    {
        protected array $actions = [
            ConditionalAction::class,
        ];
    }

    Bus::fake([
        ConditionalAction::class,
    ]);

    ConditionalWorkflow::dispatch(['id' => 1]);

    Bus::assertNotDispatched(ConditionalAction::class);
});

it('should be able to conditionally run outside a workflow', function (): void {
    Bus::fake([Temp2Action::class]);

    class Temp2Action extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    /**
     * Class ConditionalOutAction
     *
     * @property-read int $id
     */
    class ConditionalOutAction extends Action
    {
        public function handle(): self
        {
            Temp2Action::dispatch();

            return $this;
        }

        protected function runIf(): bool
        {
            return false;
        }
    }

    ConditionalOutAction::dispatch(['id' => 1]);

    Bus::assertNotDispatched(Temp2Action::class);
});

it('should return true by default when runIf is called directly on action', function (): void {
    class DefaultRunIfAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = new DefaultRunIfAction;
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('runIf');

    $result = $method->invoke($action);

    expect($result)->toBeTrue();
});

it('should return filtered array based on docblock properties using toArray method on action', function (): void {
    /**
     * @property-read string $name
     * @property-read int $age
     */
    class FilteredAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = FilteredAction::dispatchSync([
        'name' => 'John Doe',
        'age' => 30,
        'email' => 'john@example.com',
        'phone' => '123-456-7890',
    ]);

    $result = $action->toArray();

    expect($result)->toHaveKeys(['name', 'age']);
    expect($result)->not->toHaveKeys(['email', 'phone']);
    expect($result['name'])->toBe('John Doe');
    expect($result['age'])->toBe(30);
});

it('should return all payload when no docblock properties are defined using toArray method on action', function (): void {
    class NoDocBlockAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = NoDocBlockAction::dispatchSync([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '123-456-7890',
    ]);

    $result = $action->toArray();

    expect($result)->toHaveKeys(['name', 'email', 'phone']);
    expect($result['name'])->toBe('John Doe');
    expect($result['email'])->toBe('john@example.com');
    expect($result['phone'])->toBe('123-456-7890');
});

it('should return empty array when payload is empty using toArray method on action', function (): void {
    class EmptyPayloadAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = EmptyPayloadAction::dispatchSync([]);

    $result = $action->toArray();

    expect($result)->toBeEmpty();
});

it('should handle mixed payload types when using toArray method on action', function (): void {
    /**
     * @property-read string $name
     * @property-read bool $active
     * @property-read array $tags
     */
    class MixedTypeAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = MixedTypeAction::dispatchSync([
        'name' => 'John Doe',
        'active' => true,
        'tags' => ['php', 'laravel'],
        'unwanted' => 'should be filtered',
    ]);

    $result = $action->toArray();

    expect($result)->toHaveKeys(['name', 'active', 'tags']);
    expect($result)->not->toHaveKey('unwanted');
    expect($result['name'])->toBe('John Doe');
    expect($result['active'])->toBeTrue();
    expect($result['tags'])->toBe(['php', 'laravel']);
});

it('should be able to pass rules to the action to be validated using Validator facade', function (): void {
    /**
     * @property-read string $name
     * @property int $age
     */
    class RulesAction extends Action
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
        fn () => RulesAction::dispatchSync([
            'name' => 'John Doe',
            'age' => 30,
        ])
    )->not->toThrow(Illuminate\Validation\ValidationException::class);

    expect(
        fn () => RulesAction::dispatchSync([])
    )->toThrow(
        Illuminate\Validation\ValidationException::class,
        __('validation.required', ['attribute' => 'name']),
    );

    expect(
        fn () => RulesAction::dispatchSync([
            'name' => 'John Doe',
        ])
    )->toThrow(
        Illuminate\Validation\ValidationException::class,
        __('validation.required', ['attribute' => 'age']),
    );
});

it('returns middleware array containing FinalizeActionMiddleware', function (): void {
    class MiddlewareAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = MiddlewareAction::dispatchSync();
    $middlewares = $action->middleware();

    expect($middlewares)->toBeArray()
        ->and($middlewares)->toHaveCount(1)
        ->and($middlewares[0])->toBeInstanceOf(FinalizeActionMiddleware::class);
});

it('fires Processed event when finalize is called on action', function (): void {
    Event::fake();

    class FinalizeDirectAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = FinalizeDirectAction::dispatchSync();

    $action->finalize();

    Event::assertDispatched(Processed::class);
});

it('should set the queue when #[OnQueue] attribute is used on action', function (): void {
    $action = OnQueueAction::dispatch();
    $job = getActionFromReflection($action);

    expect($job->queue)->toBe('custom');
});

it('should not set queue when #[OnQueue] attribute is not used on action', function (): void {
    class NoOnQueueAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = NoOnQueueAction::dispatch();
    $job = getActionFromReflection($action);

    expect($job->queue)->toBeNull();
});

it('FinalizeActionMiddleware triggers finalize and dispatches Processed event', function (): void {
    Event::fake();

    class FinalizeMiddlewareAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    $action = FinalizeMiddlewareAction::dispatchSync();

    $middleware = new FinalizeActionMiddleware;

    $middleware->handle($action, fn ($a) => $a);

    Event::assertDispatched(Processed::class);
});

it('workflow calls finalize on action instances and fires Processed event', function (): void {
    Event::fake();

    class WorkflowFinalizeAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    class WorkflowWithFinalize extends Brain\Workflow
    {
        protected array $actions = [
            WorkflowFinalizeAction::class,
        ];
    }

    $workflow = new WorkflowWithFinalize;
    $workflow->handle();

    Event::assertDispatched(Processed::class);
});

it('queued actions are finalized before going through next middleware', function (): void {
    Event::fake();

    class QueuedAction2 extends Action implements ShouldQueue
    {
        public function handle(): self
        {
            return $this;
        }
    }

    class WorkflowQueuedAction extends Brain\Workflow
    {
        protected array $actions = [
            QueuedAction2::class,
        ];
    }

    WorkflowQueuedAction::dispatchSync();

    Event::assertDispatched(Processed::class);
});
