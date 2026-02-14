<?php

declare(strict_types=1);

use Brain\Attributes\Sensitive;
use Brain\SensitiveValue;
use Brain\Task;
use Brain\Tasks\Events\Processing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Fixtures\SensitiveProcess;
use Tests\Feature\Fixtures\SensitiveUserTask;

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

    expect($value->__debugInfo())->toBe(['value' => '**********']);
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
