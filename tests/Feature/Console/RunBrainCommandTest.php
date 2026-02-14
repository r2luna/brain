<?php

declare(strict_types=1);

use Brain\Console\RunBrainCommand;
use Tests\Feature\Fixtures\Brain\Example\Processes\ExampleProcess;
use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask;
use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask2;
use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask3;
use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4;
use Tests\Feature\Fixtures\Brain\Example2\Processes\ExampleProcess2;

beforeEach(function (): void {
    config()->set('brain.use_domains', true);
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain');
});

it('has the correct signature', function (): void {
    $command = new RunBrainCommand;
    expect($command->getName())->toBe('brain:run');
});

it('has the correct description', function (): void {
    $command = new RunBrainCommand;
    expect($command->getDescription())->toBe('Interactively run a Brain Process or Task');
});

it('shows error when no processes or tasks found', function (): void {
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain/Example3');

    $this->artisan('brain:run')
        ->assertExitCode(1);
});

it('runs a task synchronously with properties', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask::class,
            'ExampleTask',
            [
                ExampleTask::class => 'TASK  ExampleTask',
                ExampleTask2::class => 'TASK  ExampleTask2',
                ExampleTask3::class => 'TASK  ExampleTask3',
                ExampleTask4::class => 'TASK  ExampleTask4',
            ],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsQuestion('email', 'test@example.com')
        ->expectsQuestion('paymentId', '123')
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('dispatches a task asynchronously', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask3::class,
            'ExampleTask3',
            [ExampleTask3::class => 'TASK  ExampleTask3'],
        )
        ->expectsChoice('How should it be dispatched?', 'async', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('runs a process synchronously', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleProcess::class,
            'ExampleProcess',
            [
                ExampleProcess::class => 'PROCESS  ExampleProcess',
                ExampleProcess2::class => 'PROCESS  ExampleProcess2',
            ],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('handles tasks with no properties', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask3::class,
            'ExampleTask3',
            [ExampleTask3::class => 'TASK  ExampleTask3'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('cancels when user declines confirmation', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask3::class,
            'ExampleTask3',
            [ExampleTask3::class => 'TASK  ExampleTask3'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('Execute?', 'no')
        ->assertExitCode(0);
});

it('works with flat structure', function (): void {
    config()->set('brain.use_domains', false);
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain/Example');

    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask3::class,
            'ExampleTask3',
            [ExampleTask3::class => 'TASK  ExampleTask3'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('skips optional properties when declined', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask2::class,
            'ExampleTask2',
            [ExampleTask2::class => 'TASK  ExampleTask2'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsQuestion('email', 'test@example.com')
        ->expectsQuestion('paymentId', '456')
        ->expectsConfirmation('Fill optional properties?', 'no')
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('fills optional properties when accepted', function (): void {
    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            ExampleTask2::class,
            'ExampleTask2',
            [ExampleTask2::class => 'TASK  ExampleTask2'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsQuestion('email', 'test@example.com')
        ->expectsQuestion('paymentId', '456')
        ->expectsConfirmation('Fill optional properties?', 'yes')
        ->expectsQuestion('id', '99')
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('prompts bool with confirm for bool-typed properties', function (): void {
    config()->set('brain.use_domains', false);
    config()->set('brain.root', __DIR__.'/../Fixtures/RunBrain');

    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            Tests\Feature\Fixtures\RunBrain\Tasks\BoolTask::class,
            'BoolTask',
            [Tests\Feature\Fixtures\RunBrain\Tasks\BoolTask::class => 'TASK  BoolTask'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('active', 'yes')
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('prompts sensitive properties as regular text input', function (): void {
    config()->set('brain.use_domains', false);
    config()->set('brain.root', __DIR__.'/../Fixtures/RunBrain');

    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            Tests\Feature\Fixtures\RunBrain\Tasks\SecretTask::class,
            'SecretTask',
            [Tests\Feature\Fixtures\RunBrain\Tasks\SecretTask::class => 'TASK  SecretTask'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsQuestion('username', 'admin')
        ->expectsQuestion('token', 's3cret')
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(0);
});

it('catches exceptions and displays error gracefully', function (): void {
    config()->set('brain.use_domains', false);
    config()->set('brain.root', __DIR__.'/../Fixtures/RunBrain');

    $this->artisan('brain:run')
        ->expectsSearch(
            'What do you want to run?',
            Tests\Feature\Fixtures\RunBrain\Tasks\FailingTask::class,
            'FailingTask',
            [Tests\Feature\Fixtures\RunBrain\Tasks\FailingTask::class => 'TASK  FailingTask'],
        )
        ->expectsChoice('How should it be dispatched?', 'sync', [
            'sync' => 'Sync (dispatchSync)',
            'async' => 'Async (dispatch)',
        ])
        ->expectsConfirmation('Execute?', 'yes')
        ->assertExitCode(1);
});

it('unwraps SensitiveValue in formatValue', function (): void {
    $sensitive = new Brain\SensitiveValue('s3cret');
    expect(RunBrainCommand::formatValue($sensitive))->toBe('s3cret');
});

it('formats null values as string', function (): void {
    expect(RunBrainCommand::formatValue(null))->toBe('null');
});

it('formats bool values as string', function (): void {
    expect(RunBrainCommand::formatValue(true))->toBe('true');
    expect(RunBrainCommand::formatValue(false))->toBe('false');
});

it('formats array values as JSON', function (): void {
    expect(RunBrainCommand::formatValue(['a', 'b']))->toBe('["a","b"]');
    expect(RunBrainCommand::formatValue(['key' => 'value']))->toBe('{"key":"value"}');
});

it('formats scalar values as string', function (): void {
    expect(RunBrainCommand::formatValue('hello'))->toBe('hello');
    expect(RunBrainCommand::formatValue(42))->toBe('42');
});
