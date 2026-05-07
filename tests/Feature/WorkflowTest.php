<?php

declare(strict_types=1);

use Brain\Action;
use Brain\Attributes\OnQueue;
use Brain\Workflow;
use Brain\Workflows\Events\Processed;
use Brain\Workflows\Events\Processing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Fixtures\OnQueueAction;
use Tests\Feature\Fixtures\QueuedAction;
use Tests\Feature\Fixtures\SimpleAction;

test('workflow can be instantiated with payload', function (): void {
    $payload = ['test' => 'value'];
    $workflow = new Workflow($payload);

    expect($workflow)->toBeInstanceOf(Workflow::class)
        ->and($workflow->getActions())->toBeArray()
        ->and($workflow->getActions())->toBeEmpty();
});

test('workflow fires events when handled', function (): void {
    Event::fake();
    $workflow = new Workflow(['test' => 'value']);

    $workflow->handle();

    Event::assertDispatched(Processing::class);
    Event::assertDispatched(Processed::class);
});

test('workflow can run actions synchronously', function (): void {
    $payload = (object) ['value' => 0];
    $workflow = new Workflow($payload);
    $workflow->addAction(SimpleAction::class);

    $result = $workflow->handle();

    expect($result->value)->toBe(1);
});

test('workflow can chain actions', function (): void {
    Bus::fake();

    $payload = (object) ['test' => 'value'];
    $workflow = new Workflow($payload);
    $workflow->addAction(SimpleAction::class);
    $workflow->addAction(SimpleAction::class);

    $reflection = new ReflectionClass($workflow);
    $chainProperty = $reflection->getProperty('chain');
    $chainProperty->setValue($workflow, true);

    $workflow->handle();

    Bus::assertChained([
        SimpleAction::class,
        SimpleAction::class,
    ]);
});

test('queued actions are dispatched separately', function (): void {
    Bus::fake();

    $payload = (object) ['test' => 'value'];
    $workflow = new Workflow($payload);
    $workflow->addAction(QueuedAction::class);

    $workflow->handle();

    Bus::assertDispatched(QueuedAction::class);
});

test('workflow can be cancelled during execution', function (): void {
    $payload = (object) [
        'value' => 0,
        'cancelWorkflow' => true,
    ];

    $workflow = new Workflow($payload);
    $workflow->addAction(SimpleAction::class);
    $workflow->addAction(SimpleAction::class);

    $result = $workflow->handle();

    expect($result->value)->toBe(0);
});

test('workflow maintains action order', function (): void {
    $payload = (object) ['value' => 0];
    $workflow = new Workflow($payload);

    $workflow->addAction(SimpleAction::class);

    expect($workflow->getActions())->toHaveCount(1)
        ->and($workflow->getActions()[0])->toBe(SimpleAction::class);
});

test('workflow can handle null payload', function (): void {
    $workflow = new Workflow;

    expect(fn (): object|array|null => $workflow->handle())->not->toThrow(Exception::class);
});

test('workflow converts array payload to object', function (): void {
    $workflow = new Workflow(['key' => 'value']);
    $workflow->addAction(SimpleAction::class);

    $result = $workflow->handle();

    expect($result)->toBeObject();
});

it('should be possible to set the workflow as chain', function (): void {
    class ChainWorkflow extends Workflow
    {
        protected bool $chain = true;
    }

    $workflow = new ChainWorkflow(['test' => 'value']);
    $workflow->addAction(SimpleAction::class);

    expect($workflow->isChained())->toBeTrue();
});

test('when workflow is set as chain we need to dispatch a chained bus', function (): void {
    class QueueableAction extends Action implements ShouldQueue {}
    class Queueable2Action extends Action implements ShouldQueue {}
    class Chain2Workflow extends Workflow
    {
        protected bool $chain = true;

        protected array $actions = [
            QueueableAction::class,
            Queueable2Action::class,
        ];
    }

    Bus::fake([
        QueueableAction::class,
        Queueable2Action::class,
    ]);

    Chain2Workflow::dispatchSync([]);

    Bus::assertChained([
        QueueableAction::class,
        Queueable2Action::class,
    ]);

});

test('making sure the action is dispatch to the queue when ShouldQueue is implemented', function (): void {
    class ShouldQueueableAction extends Action implements ShouldQueue {}
    class ShouldQueueableAftercommitAction extends Action implements ShouldQueueAfterCommit {}
    class ShouldQueueableWorkflow extends Workflow
    {
        protected array $actions = [
            ShouldQueueableAction::class,
            ShouldQueueableAftercommitAction::class,
        ];
    }

    Bus::fake([ShouldQueueableAction::class, ShouldQueueableAftercommitAction::class]);

    ShouldQueueableWorkflow::dispatchSync([]);

    Bus::assertDispatched(ShouldQueueableAction::class);
    Bus::assertDispatched(ShouldQueueableAftercommitAction::class);
});

it('should be possible to cancelWorkflow from the payload if the next _action_ is a workflow', function (): void {
    class SubWorkflowCancelAction extends Action
    {
        public function handle(): self
        {
            $this->cancelWorkflow();

            return $this;
        }
    }

    class CancelWorkflow extends Workflow
    {
        protected array $actions = [
            CancelSubWorkflow::class,
            SimpleAction::class,
        ];
    }

    class CancelSubWorkflow extends Workflow
    {
        protected array $actions = [
            SubWorkflowCancelAction::class,
        ];
    }

    Bus::fake([SimpleAction::class]);

    CancelWorkflow::dispatch([]);

    Bus::assertDispatched(SimpleAction::class, function (SimpleAction $action): true {
        expect($action->payload)->not->toHaveKey('cancelWorkflow');

        return true;
    });
});

test('if we get an exception in a workflow we should rollback any change occurred in the database', function (): void {

    class ExceptionAction extends Action
    {
        public function handle(): self
        {
            throw new Exception('Action failed');
        }
    }

    class ExceptionWorkflow extends Workflow
    {
        protected array $actions = [
            ExceptionAction::class,
        ];
    }

    DB::shouldReceive('beginTransaction')->once();
    DB::shouldReceive('rollBack')->once();

    try {
        ExceptionWorkflow::dispatchSync([]);
    } catch (Throwable) {
        // expected
    }

});

it('should return the final payload as an object no matter what', function (): void {
    class ArrayPayloadAction extends Action
    {
        public function handle(): array
        {
            return ['value' => 1];
        }
    }

    class ObjectPayloadAction extends Action
    {
        public function handle(): object
        {
            return (object) ['value' => 1];
        }
    }

    class WorkflowWithArrayPayload extends Workflow
    {
        protected array $actions = [
            ArrayPayloadAction::class,
        ];
    }

    class WorkflowWithObjectPayload extends Workflow
    {
        protected array $actions = [
            ObjectPayloadAction::class,
        ];
    }

    $arrayPayload = WorkflowWithArrayPayload::dispatchSync([]);
    $objectPayload = WorkflowWithObjectPayload::dispatchSync([]);

    expect($arrayPayload)->toBeObject();
    expect($objectPayload)->toBeObject();
});

it('should return the original workflow class name', function (): void {
    Event::fake();

    class PayloadAction extends Action
    {
        public function handle(): array
        {
            return ['value' => 1];
        }
    }

    class WorkflowPayload extends Workflow
    {
        protected array $actions = [
            PayloadAction::class,
        ];
    }

    WorkflowPayload::dispatchSync([]);

    Event::assertDispatched(Processing::class, fn (Processing $event): bool => $event->workflow === WorkflowPayload::class);
});

it('should dispatch queued actions to the workflow queue when #[OnQueue] is set on workflow', function (): void {
    Bus::fake([QueuedAction::class]);

    #[OnQueue('strava')]
    class OnQueueWorkflow extends Workflow
    {
        protected array $actions = [
            QueuedAction::class,
        ];
    }

    OnQueueWorkflow::dispatchSync([]);

    Bus::assertDispatched(QueuedAction::class, fn (QueuedAction $action): bool => $action->queue === 'strava');
});

it('should let action #[OnQueue] override workflow #[OnQueue]', function (): void {
    Bus::fake([OnQueueAction::class]);

    #[OnQueue('workflow-queue')]
    class OverrideQueueWorkflow extends Workflow
    {
        protected array $actions = [
            OnQueueAction::class,
        ];
    }

    OverrideQueueWorkflow::dispatchSync([]);

    Bus::assertDispatched(OnQueueAction::class, fn (OnQueueAction $action): bool => $action->queue === 'custom');
});

it('should not affect non-queued actions when #[OnQueue] is set on workflow', function (): void {
    #[OnQueue('strava')]
    class OnQueueSyncWorkflow extends Workflow
    {
        protected array $actions = [
            SimpleAction::class,
        ];
    }

    $result = (new OnQueueSyncWorkflow(['value' => 0]))->handle();

    expect($result->value)->toBe(1);
});

it('should run synchronously using the static run method', function (): void {
    class RunWorkflow extends Workflow
    {
        protected array $actions = [
            SimpleAction::class,
        ];
    }

    $result = RunWorkflow::run(['value' => 0]);

    expect($result)->toBeObject()
        ->and($result->value)->toBe(1);
});

it('should apply workflow #[OnQueue] to chained actions', function (): void {
    class OnQueueChainAction extends Action implements ShouldQueue {}
    class OnQueueChainAction2 extends Action implements ShouldQueue {}

    Bus::fake([OnQueueChainAction::class, OnQueueChainAction2::class]);

    #[OnQueue('strava')]
    class OnQueueChainWorkflow extends Workflow
    {
        protected bool $chain = true;

        protected array $actions = [
            OnQueueChainAction::class,
            OnQueueChainAction2::class,
        ];
    }

    OnQueueChainWorkflow::dispatchSync([]);

    Bus::assertChained([
        OnQueueChainAction::class,
        OnQueueChainAction2::class,
    ]);
});

describe('lifecycle hooks', function (): void {
    it('should call before() to transform the payload before dispatch', function (): void {
        class HookedBeforeAction extends Action
        {
            public function handle(): self
            {
                $this->payload->seen = $this->payload->value;

                return $this;
            }
        }

        class HookedBeforeWorkflow extends Workflow
        {
            protected array $actions = [HookedBeforeAction::class];

            protected static function before(array|object|null $payload): array|object|null
            {
                $payload['value'] = ($payload['value'] ?? 0) + 100;

                return $payload;
            }
        }

        $result = HookedBeforeWorkflow::run(['value' => 1]);

        expect($result->seen)->toBe(101);
    });

    it('should call after() to transform the result after dispatch', function (): void {
        class HookedAfterWorkflow extends Workflow
        {
            protected array $actions = [SimpleAction::class];

            protected static function after(object|array|null $result): object|array|null
            {
                $result->wrapped = true;

                return $result;
            }
        }

        $result = HookedAfterWorkflow::run(['value' => 0]);

        expect($result->value)->toBe(1)
            ->and($result->wrapped)->toBeTrue();
    });

    it('should call onError() when the workflow throws and let it return a fallback', function (): void {
        class HookedFailingAction extends Action
        {
            public function handle(): self
            {
                throw new RuntimeException('boom');
            }
        }

        class HookedRecoveringWorkflow extends Workflow
        {
            protected array $actions = [HookedFailingAction::class];

            protected static function onError(Throwable $e, array|object|null $payload): object|array|null
            {
                return (object) ['fallback' => true, 'error' => $e->getMessage()];
            }
        }

        $result = HookedRecoveringWorkflow::run(['value' => 0]);

        expect($result->fallback)->toBeTrue()
            ->and($result->error)->toBe('boom');
    });

    it('should re-throw by default when onError() is not overridden', function (): void {
        class HookedDefaultErrorAction extends Action
        {
            public function handle(): self
            {
                throw new RuntimeException('uncaught');
            }
        }

        class HookedDefaultErrorWorkflow extends Workflow
        {
            protected array $actions = [HookedDefaultErrorAction::class];
        }

        HookedDefaultErrorWorkflow::run([]);
    })->throws(RuntimeException::class, 'uncaught');

    it('should call finally() on the happy path with no error', function (): void {
        class HookedFinallyAction extends Action
        {
            public function handle(): self
            {
                return $this;
            }
        }

        $captured = new stdClass;
        $captured->called = false;
        $captured->error = 'unset';
        $GLOBALS['__finally_capture'] = $captured;

        class HookedFinallyHappyWorkflow extends Workflow
        {
            protected array $actions = [HookedFinallyAction::class];

            protected static function finally(array|object|null $payload, ?Throwable $error): void
            {
                $GLOBALS['__finally_capture']->called = true;
                $GLOBALS['__finally_capture']->error = $error;
            }
        }

        HookedFinallyHappyWorkflow::run([]);

        expect($captured->called)->toBeTrue()
            ->and($captured->error)->toBeNull();
    });

    it('should call finally() on the error path with the exception', function (): void {
        class HookedFinallyErrorAction extends Action
        {
            public function handle(): self
            {
                throw new RuntimeException('kaboom');
            }
        }

        $captured = new stdClass;
        $captured->called = false;
        $captured->error = 'unset';
        $GLOBALS['__finally_capture_err'] = $captured;

        class HookedFinallyErrorWorkflow extends Workflow
        {
            protected array $actions = [HookedFinallyErrorAction::class];

            protected static function onError(Throwable $e, array|object|null $payload): object|array|null
            {
                return null;
            }

            protected static function finally(array|object|null $payload, ?Throwable $error): void
            {
                $GLOBALS['__finally_capture_err']->called = true;
                $GLOBALS['__finally_capture_err']->error = $error;
            }
        }

        HookedFinallyErrorWorkflow::run([]);

        expect($captured->called)->toBeTrue()
            ->and($captured->error)->toBeInstanceOf(RuntimeException::class)
            ->and($captured->error->getMessage())->toBe('kaboom');
    });

    it('should fire hooks of a sub-workflow when invoked from a parent workflow', function (): void {
        class SubHookedAction extends Action
        {
            public function handle(): self
            {
                return $this;
            }
        }

        class SubHookedWorkflow extends Workflow
        {
            protected array $actions = [SubHookedAction::class];

            protected static function before(array|object|null $payload): array|object|null
            {
                $payload->subBeforeCalled = true;

                return $payload;
            }

            protected static function after(object|array|null $result): object|array|null
            {
                $result->subAfterCalled = true;

                return $result;
            }
        }

        class ParentWithSubWorkflow extends Workflow
        {
            protected array $actions = [SubHookedWorkflow::class];
        }

        $result = ParentWithSubWorkflow::run((object) []);

        expect($result->subBeforeCalled)->toBeTrue()
            ->and($result->subAfterCalled)->toBeTrue();
    });
});
