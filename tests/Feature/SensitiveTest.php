<?php

declare(strict_types=1);

use Brain\Action;
use Brain\Actions\Events\Cancelled as ActionCancelled;
use Brain\Actions\Events\Error as ActionError;
use Brain\Actions\Events\Processing as ActionProcessing;
use Brain\Actions\Middleware\FinalizeActionMiddleware;
use Brain\Attributes\Sensitive;
use Brain\Console\BrainMap;
use Brain\Processes\Events\Error as ProcessError;
use Brain\SensitiveValue;
use Brain\Task;
use Brain\Tasks\Events\Cancelled as TaskCancelled;
use Brain\Tasks\Events\Error as TaskError;
use Brain\Tasks\Events\Processing;
use Brain\Tasks\Middleware\FinalizeTaskMiddleware;
use Brain\Workflows\Events\Error as WorkflowError;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Tests\Feature\Fixtures\Sensitive\CancelBeforeSecretProcess;
use Tests\Feature\Fixtures\Sensitive\CancelBeforeSecretWorkflow;
use Tests\Feature\Fixtures\Sensitive\InheritedSensitiveAction;
use Tests\Feature\Fixtures\Sensitive\InheritedSensitiveProcess;
use Tests\Feature\Fixtures\Sensitive\InheritedSensitiveTask;
use Tests\Feature\Fixtures\Sensitive\InheritedSensitiveWorkflow;
use Tests\Feature\Fixtures\Sensitive\LeakyProcess;
use Tests\Feature\Fixtures\Sensitive\LeakyWorkflow;
use Tests\Feature\Fixtures\Sensitive\NestedSecretAction;
use Tests\Feature\Fixtures\Sensitive\ParentSensitiveProcess;
use Tests\Feature\Fixtures\Sensitive\ParentSensitiveWorkflow;
use Tests\Feature\Fixtures\Sensitive\RecordingAction;
use Tests\Feature\Fixtures\Sensitive\RecordingTask;
use Tests\Feature\Fixtures\Sensitive\ValidatedSecretAction;
use Tests\Feature\Fixtures\Sensitive\ValidatedSecretTask;
use Tests\Feature\Fixtures\Sensitive\ValidatingProcess;
use Tests\Feature\Fixtures\Sensitive\ValidatingWorkflow;
use Tests\Feature\Fixtures\SensitiveProcess;
use Tests\Feature\Fixtures\SensitiveUserAction;
use Tests\Feature\Fixtures\SensitiveUserTask;
use Tests\Feature\Fixtures\SensitiveWorkflow;

// ── Sensitive Attribute ──

it('returns the correct keys from the Sensitive attribute', function (): void {
    $sensitive = new Sensitive('password', 'credit_card');

    expect($sensitive->keys)->toBe(['password', 'credit_card']);
});

it('returns empty array when no Sensitive attribute is present', function (): void {
    class NoSensitiveTask extends Task
    {
        public function handle(): self
        {
            return $this;
        }
    }

    expect(NoSensitiveTask::getSensitiveKeys())->toBe([]);
});

it('returns declared keys from getSensitiveKeys', function (): void {
    expect(SensitiveUserTask::getSensitiveKeys())->toBe(['password', 'credit_card']);
});

// ── SensitiveValue ──

it('wraps sensitive payload keys in SensitiveValue during construction', function (): void {
    $task = SensitiveUserTask::dispatchSync([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    expect($task->payload->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($task->payload->credit_card)->toBeInstanceOf(SensitiveValue::class)
        ->and($task->payload->email)->toBe('john@example.com');
});

it('unwraps SensitiveValue transparently via __get', function (): void {
    $task = SensitiveUserTask::dispatchSync([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    expect($task->password)->toBe('secret123')
        ->and($task->credit_card)->toBe('4111111111111111')
        ->and($task->email)->toBe('john@example.com');
});

it('auto-wraps when setting a sensitive key via __set', function (): void {
    $task = SensitiveUserTask::dispatchSync([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    $task->password = 'new_password';

    expect($task->payload->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($task->password)->toBe('new_password');
});

it('redacts sensitive values when cast to string', function (): void {
    $value = new SensitiveValue('secret123');

    expect((string) $value)->toBe('**********');
});

it('redacts sensitive values when json serialized', function (): void {
    $value = new SensitiveValue('secret123');

    expect(json_encode($value, JSON_UNESCAPED_UNICODE))->toBe('"**********"');
});

it('redacts sensitive values in debug info', function (): void {
    $value = new SensitiveValue('secret123');

    expect($value->__debugInfo())->toBe(["\0".SensitiveValue::class."\0value" => '**********']);
});

it('returns the real value via value()', function (): void {
    $value = new SensitiveValue('secret123');

    expect($value->value())->toBe('secret123');
});

// ── Events ──

it('fires events with SensitiveValue in payload', function (): void {
    Event::fake([Processing::class]);

    SensitiveUserTask::dispatch([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    Event::assertDispatched(Processing::class, fn (Processing $event): bool => $event->payload->email === 'john@example.com'
        && $event->payload->password instanceof SensitiveValue
        && $event->payload->credit_card instanceof SensitiveValue
        && (string) $event->payload->password === '**********'
        && (string) $event->payload->credit_card === '**********');
});

it('redacts sensitive values when event payload is json encoded', function (): void {
    Event::fake([Processing::class]);

    SensitiveUserTask::dispatch([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    Event::assertDispatched(Processing::class, function (Processing $event): bool {
        $json = json_encode($event->payload);
        $decoded = json_decode($json, true);

        return $decoded['email'] === 'john@example.com'
            && $decoded['password'] === '**********'
            && $decoded['credit_card'] === '**********';
    });
});

// ── Process-level Sensitive inheritance ──

it('inherits sensitive keys from the process to tasks without #[Sensitive]', function (): void {
    $process = new SensitiveProcess([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    $result = $process->handle();

    expect($result->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($result->credit_card)->toBeInstanceOf(SensitiveValue::class)
        ->and($result->email)->toBe('john@example.com');
});

it('merges process-level and task-level sensitive keys', function (): void {
    Context::add('brain.sensitive_keys', ['token']);

    $keys = SensitiveUserTask::getSensitiveKeys();

    expect($keys)->toContain('password')
        ->and($keys)->toContain('credit_card')
        ->and($keys)->toContain('token');
});

it('deduplicates sensitive keys when process and task declare the same key', function (): void {
    Context::add('brain.sensitive_keys', ['password', 'api_key']);

    $keys = SensitiveUserTask::getSensitiveKeys();

    expect($keys)->toEqualCanonicalizing(['password', 'credit_card', 'api_key']);
});

// ── Action Sensitive Attribute ──

it('wraps sensitive payload keys in SensitiveValue during Action construction', function (): void {
    $action = SensitiveUserAction::dispatchSync([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    expect($action->payload->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($action->payload->credit_card)->toBeInstanceOf(SensitiveValue::class)
        ->and($action->payload->email)->toBe('john@example.com');
});

it('auto-wraps when setting a sensitive key via __set on Action', function (): void {
    $action = SensitiveUserAction::dispatchSync([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    $action->password = 'new_password';

    expect($action->payload->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($action->password)->toBe('new_password');
});

it('merges workflow-level and action-level sensitive keys', function (): void {
    Context::add('brain.sensitive_keys', ['token']);

    $keys = SensitiveUserAction::getSensitiveKeys();

    expect($keys)->toContain('password')
        ->and($keys)->toContain('credit_card')
        ->and($keys)->toContain('token');
});

it('returns empty sensitive keys for Action without #[Sensitive]', function (): void {
    class NoSensitiveAction extends Action
    {
        public function handle(): self
        {
            return $this;
        }
    }

    expect(NoSensitiveAction::getSensitiveKeys())->toBe([]);
});

// ── Workflow-level Sensitive inheritance ──

it('inherits sensitive keys from the workflow to actions without #[Sensitive]', function (): void {
    $workflow = new SensitiveWorkflow([
        'email' => 'john@example.com',
        'password' => 'secret123',
        'credit_card' => '4111111111111111',
    ]);

    $result = $workflow->handle();

    expect($result->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($result->credit_card)->toBeInstanceOf(SensitiveValue::class)
        ->and($result->email)->toBe('john@example.com');
});

// ── Regression: leaks of #[Sensitive] values ──

dataset('flows', [
    'workflow' => [[
        'parent' => ParentSensitiveWorkflow::class,
        'recording' => RecordingAction::class,
        'validating' => ValidatingWorkflow::class,
        'cancel' => CancelBeforeSecretWorkflow::class,
        'leaky' => LeakyWorkflow::class,
        'inherited' => InheritedSensitiveWorkflow::class,
        'inheritedUnit' => InheritedSensitiveAction::class,
        'validatedUnit' => ValidatedSecretAction::class,
        'sensitive' => SensitiveWorkflow::class,
        'sensitiveUnit' => SensitiveUserAction::class,
        'processing' => ActionProcessing::class,
        'cancelled' => ActionCancelled::class,
        'error' => ActionError::class,
        'flowError' => WorkflowError::class,
    ]],
    'process' => [[
        'parent' => ParentSensitiveProcess::class,
        'recording' => RecordingTask::class,
        'validating' => ValidatingProcess::class,
        'cancel' => CancelBeforeSecretProcess::class,
        'leaky' => LeakyProcess::class,
        'inherited' => InheritedSensitiveProcess::class,
        'inheritedUnit' => InheritedSensitiveTask::class,
        'validatedUnit' => ValidatedSecretTask::class,
        'sensitive' => SensitiveProcess::class,
        'sensitiveUnit' => SensitiveUserTask::class,
        'processing' => Processing::class,
        'cancelled' => TaskCancelled::class,
        'error' => TaskError::class,
        'flowError' => ProcessError::class,
    ]],
]);

/** Records the JSON of each event payload and meta at the moment the event fires. */
function captureEvents(string ...$events): ArrayObject
{
    $captured = new ArrayObject;

    Event::listen($events, function (object $event) use ($captured): void {
        $captured[] = json_encode([$event->payload, $event->meta]);
    });

    return $captured;
}

it('F1: merges the parent sensitive keys into a sub-flow without #[Sensitive]', function (array $flow): void {
    $flow['recording']::$keys = [];

    $flow['parent']::run(['email' => 'john@example.com', 'password' => 'secret123']);

    expect($flow['recording']::$keys)->toContain('password');
})->with('flows');

it('F2: redacts the error event payload when validation fails inside a flow', function (array $flow): void {
    $captured = captureEvents($flow['error']);

    expect(fn () => $flow['validating']::run(['password' => 'secret123']))
        ->toThrow(ValidationException::class);

    expect($captured)->not->toBeEmpty()
        ->and(implode('', (array) $captured))->not->toContain('secret123');
})->with('flows');

it('F3: redacts keys declared by later actions in earlier and cancelled events', function (array $flow): void {
    $captured = captureEvents($flow['processing'], $flow['cancelled']);

    $flow['cancel']::run(['email' => 'john@example.com', 'password' => 'secret123']);

    expect($captured)->toHaveCount(2)
        ->and(implode('', (array) $captured))->not->toContain('secret123');
})->with('flows');

it('F4: encrypts sensitive values when serialized', function (): void {
    $serialized = serialize(new SensitiveValue('secret123'));

    expect($serialized)->not->toContain('secret123')
        ->and(unserialize($serialized)->value())->toBe('secret123');
});

it('F4: does not store sensitive payload values in plain text in queued jobs', function (array $flow): void {
    $payload = ['email' => 'john@example.com', 'password' => 'secret123'];

    $flowJob = serialize(new $flow['sensitive']($payload));
    $unitJob = serialize(new $flow['sensitiveUnit']($payload));

    expect($flowJob)->not->toContain('secret123')
        ->and($unitJob)->not->toContain('secret123')
        ->and(unserialize($unitJob)->password)->toBe('secret123');
})->with('flows');

it('F5: hides the real value from VarDumper and print_r', function (): void {
    $value = new SensitiveValue('secret123');

    $output = call_user_func([new CliDumper, 'dump'], (new VarCloner)->cloneVar($value), true);

    expect($output)->not->toContain('secret123')
        ->and($output)->toContain('**********')
        ->and(print_r($value, true))->not->toContain('secret123');
});

it('F6: inherits #[Sensitive] declared on a parent class', function (array $flow): void {
    $flow['recording']::$keys = [];

    $flow['inherited']::run(['email' => 'john@example.com', 'password' => 'secret123']);

    expect($flow['inheritedUnit']::getSensitiveKeys())->toBe(['password'])
        ->and($flow['recording']::$keys)->toContain('password');
})->with('flows');

it('F6: flags inherited sensitive properties in the brain map', function (): void {
    config()->set('brain.root', __DIR__.'/Fixtures/Brain');

    $properties = (new ReflectionMethod(BrainMap::class, 'getPropertiesFor'))
        ->invoke(new BrainMap, new ReflectionClass(InheritedSensitiveAction::class));

    expect(collect($properties)->firstWhere('name', 'password')['sensitive'])->toBeTrue()
        ->and(collect($properties)->firstWhere('name', 'email')['sensitive'])->toBeFalse();
});

it('F10: redacts sensitive values from error messages in event meta', function (array $flow): void {
    $captured = captureEvents($flow['error'], $flow['flowError']);

    expect(fn () => $flow['leaky']::run(['password' => 'secret123']))
        ->toThrow(RuntimeException::class);

    expect($captured)->toHaveCount(2)
        ->and(implode('', (array) $captured))->not->toContain('secret123')
        ->and(implode('', (array) $captured))->toContain('Invalid credentials: **********');
})->with('flows');

it('F10: redacts sensitive values from error messages in the finalize middleware', function (string $middleware, string $unit, string $event): void {
    $captured = captureEvents($event);
    $instance = new $unit(['email' => 'john@example.com', 'password' => 'secret123']);

    expect(fn () => (new $middleware)->handle($instance, fn () => throw new RuntimeException('Invalid credentials: secret123')))
        ->toThrow(RuntimeException::class);

    expect($captured)->toHaveCount(1)
        ->and($captured[0])->not->toContain('secret123')
        ->and($captured[0])->toContain('Invalid credentials: **********');
})->with([
    'action' => [FinalizeActionMiddleware::class, SensitiveUserAction::class, ActionError::class],
    'task' => [FinalizeTaskMiddleware::class, SensitiveUserTask::class, TaskError::class],
]);

it('F11: wraps sensitive keys declared with dot notation', function (): void {
    $payload = NestedSecretAction::run(['user' => ['name' => 'John', 'password' => 'secret123']]);

    expect($payload->user['password'])->toBeInstanceOf(SensitiveValue::class)
        ->and($payload->user['name'])->toBe('John')
        ->and(json_encode($payload))->not->toContain('secret123');
});

it('validates rules against the unwrapped sensitive values', function (array $flow): void {
    $unit = $flow['validatedUnit']::dispatchSync([
        'email' => 'john@example.com',
        'password' => new SensitiveValue('secret123'),
    ]);

    expect($unit->payload->password)->toBeInstanceOf(SensitiveValue::class)
        ->and($unit->password)->toBe('secret123');
})->with('flows');

it('unwraps nested arrays and objects without touching the original', function (): void {
    $payload = (object) ['user' => (object) ['password' => new SensitiveValue('secret123')], 'tags' => [new SensitiveValue('a')]];

    $unwrapped = SensitiveValue::unwrap($payload);

    expect($unwrapped->user->password)->toBe('secret123')
        ->and($unwrapped->tags)->toBe(['a'])
        ->and($payload->user->password)->toBeInstanceOf(SensitiveValue::class);
});

it('redacts nested sensitive scalars, longest first, ignoring non-sensitive values', function (): void {
    $payload = (object) [
        'email' => 'john@example.com',
        'card' => new SensitiveValue(['number' => 4111111111111111, 'cvv' => '41', 'active' => true, 'note' => '']),
        'pin' => new SensitiveValue('4111'),
    ];

    $message = SensitiveValue::redact('card 4111111111111111 pin 4111 for john@example.com', $payload);

    expect($message)->toBe('card ********** pin ********** for john@example.com');
});
