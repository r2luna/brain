<?php

declare(strict_types=1);

use Brain\Console\Support\RunHistory;
use Illuminate\Support\Facades\File;

afterEach(function (): void {
    $path = storage_path('brain/run-history-test.json');
    if (File::exists($path)) {
        File::delete($path);
    }
});

it('records and retrieves entries', function (): void {
    $path = storage_path('brain/run-history-test.json');
    $history = new RunHistory($path);

    $history->record(
        ['class' => 'App\\Tasks\\ExampleTask', 'type' => 'task'],
        ['email' => 'test@example.com'],
        true,
    );

    $entries = $history->all();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['class'])->toBe('App\\Tasks\\ExampleTask')
        ->and($entries[0]['type'])->toBe('task')
        ->and($entries[0]['sync'])->toBeTrue()
        ->and($entries[0]['payload'])->toBe(['email' => 'test@example.com'])
        ->and($entries[0]['timestamp'])->not->toBeEmpty();
});

it('most recent entry appears first', function (): void {
    $path = storage_path('brain/run-history-test.json');
    $history = new RunHistory($path);

    $history->record(
        ['class' => 'App\\Tasks\\First', 'type' => 'task'],
        [],
        true,
    );

    $history->record(
        ['class' => 'App\\Tasks\\Second', 'type' => 'task'],
        [],
        false,
    );

    $entries = $history->all();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['class'])->toBe('App\\Tasks\\Second')
        ->and($entries[1]['class'])->toBe('App\\Tasks\\First');
});

it('enforces max 50 entries', function (): void {
    $path = storage_path('brain/run-history-test.json');
    $history = new RunHistory($path);

    for ($i = 0; $i < 55; $i++) {
        $history->record(
            ['class' => "App\\Tasks\\Task{$i}", 'type' => 'task'],
            [],
            true,
        );
    }

    $entries = $history->all();

    expect($entries)->toHaveCount(50)
        ->and($entries[0]['class'])->toBe('App\\Tasks\\Task54');
});

it('handles missing file gracefully', function (): void {
    $path = storage_path('brain/run-history-test-missing.json');
    $history = new RunHistory($path);

    expect($history->all())->toBe([]);
});

it('handles corrupted JSON gracefully', function (): void {
    $path = storage_path('brain/run-history-test.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'not valid json{{{');

    $history = new RunHistory($path);

    expect($history->all())->toBe([]);
});

it('default factory returns a valid instance', function (): void {
    $history = RunHistory::default();

    expect($history)->toBeInstanceOf(RunHistory::class);
});
