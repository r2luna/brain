<?php

declare(strict_types=1);

use Brain\Action;
use Brain\Workflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $GLOBALS['__hook_log'] = [];
});

class QH_LogAction extends Action
{
    public static function before(array|object|null $payload): array|object|null
    {
        $GLOBALS['__hook_log'][] = 'before';

        return $payload;
    }

    public static function after(Action $result): static
    {
        $GLOBALS['__hook_log'][] = 'after';

        return $result;
    }

    public static function finally(array|object|null $payload, ?Throwable $error): void
    {
        $GLOBALS['__hook_log'][] = 'finally:'.($error?->getMessage() ?? 'ok');
    }

    public function handle(): self
    {
        $GLOBALS['__hook_log'][] = 'handle';

        return $this;
    }
}

class QH_QueuedLogAction extends QH_LogAction implements ShouldQueue {}

class QH_FailingQueuedAction extends Action implements ShouldQueue
{
    public static function onError(Throwable $e, array|object|null $payload): mixed
    {
        $GLOBALS['__hook_log'][] = 'onError:'.$e->getMessage();

        return 'IGNORED_IN_QUEUED';
    }

    public static function finally(array|object|null $payload, ?Throwable $error): void
    {
        $GLOBALS['__hook_log'][] = 'finally:'.($error?->getMessage() ?? 'ok');
    }

    public function handle(): self
    {
        $GLOBALS['__hook_log'][] = 'handle';

        throw new RuntimeException('queued boom');
    }
}

class QH_LogWorkflow extends Workflow
{
    protected array $actions = [QH_LogAction::class];

    public static function before(array|object|null $payload): array|object|null
    {
        $GLOBALS['__hook_log'][] = 'wf:before';

        return $payload;
    }

    public static function after(object|array|null $result): object|array|null
    {
        $GLOBALS['__hook_log'][] = 'wf:after';

        return $result;
    }

    public static function finally(array|object|null $payload, ?Throwable $error): void
    {
        $GLOBALS['__hook_log'][] = 'wf:finally:'.($error?->getMessage() ?? 'ok');
    }
}

class QH_QueuedLogWorkflow extends QH_LogWorkflow implements ShouldQueue {}

class QH_ChainedWorkflow extends Workflow
{
    protected bool $chain = true;

    protected array $actions = [QH_QueuedLogAction::class];

    public static function before(array|object|null $payload): array|object|null
    {
        $GLOBALS['__hook_log'][] = 'chain-wf:before';

        return $payload;
    }

    public static function after(object|array|null $result): object|array|null
    {
        $GLOBALS['__hook_log'][] = 'chain-wf:after';

        return $result;
    }

    public static function finally(array|object|null $payload, ?Throwable $error): void
    {
        $GLOBALS['__hook_log'][] = 'chain-wf:finally:'.($error?->getMessage() ?? 'ok');
    }
}

class QH_OuterWorkflow extends Workflow
{
    protected array $actions = [QH_QueuedLogAction::class];
}

it('fires action hooks when the action is dispatched to the queue', function (): void {
    QH_QueuedLogAction::dispatch();

    expect($GLOBALS['__hook_log'])->toBe([
        'before',
        'handle',
        'after',
        'finally:ok',
    ]);
});

it('fires workflow hooks when the workflow is dispatched to the queue', function (): void {
    QH_QueuedLogWorkflow::dispatch();

    expect($GLOBALS['__hook_log'])->toContain('wf:before')
        ->and($GLOBALS['__hook_log'])->toContain('wf:after')
        ->and($GLOBALS['__hook_log'])->toContain('wf:finally:ok');
});

it('fires action hooks when a ShouldQueue action runs inside a workflow', function (): void {
    QH_OuterWorkflow::run();

    expect($GLOBALS['__hook_log'])->toContain('before')
        ->and($GLOBALS['__hook_log'])->toContain('handle')
        ->and($GLOBALS['__hook_log'])->toContain('after')
        ->and($GLOBALS['__hook_log'])->toContain('finally:ok');
});

it('does not fire workflow hooks for chained workflows', function (): void {
    Bus::fake();

    QH_ChainedWorkflow::dispatch();

    expect($GLOBALS['__hook_log'])->not->toContain('chain-wf:before')
        ->and($GLOBALS['__hook_log'])->not->toContain('chain-wf:after')
        ->and($GLOBALS['__hook_log'])->not->toContain('chain-wf:finally:ok');
});

it('always re-throws and ignores onError return in queued context', function (): void {
    $caught = null;
    try {
        QH_FailingQueuedAction::dispatch();
    } catch (Throwable $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RuntimeException::class)
        ->and($caught->getMessage())->toBe('queued boom')
        ->and($GLOBALS['__hook_log'])->toContain('onError:queued boom')
        ->and($GLOBALS['__hook_log'])->toContain('finally:queued boom');
});
