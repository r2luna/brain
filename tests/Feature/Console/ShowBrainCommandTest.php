<?php

declare(strict_types=1);

use Brain\Console\ShowBrainCommand;
use Brain\Facades\Terminal;
use Illuminate\Console\OutputStyle;

beforeEach(function (): void {
    config()->set('brain.use_domains', true);
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain');
    Terminal::shouldReceive('cols')->andReturn(71);
    $this->command = new ShowBrainCommand;
});

it('has the correct signature', function (): void {
    expect($this->command->getName())->toBe('brain:show');
});

it('has the correct description', function (): void {
    expect($this->command->getDescription())->toBe('Show Brain Mapping');
});

it('executes the command successfully', function (): void {
    $mockOutput = Mockery::mock(OutputStyle::class);
    $mockOutput->shouldReceive('writeln')->once();
    $mockOutput->shouldReceive('isVerbose')->andReturn(false);
    $mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);

    $this->command->setOutput($mockOutput);

    $this->command->handle();
});

it('throws exception when brain is empty', function (): void {
    config()->set('brain.root', __DIR__.'/../Fixtures/EmptyBrain');

    $this->command->handle();
})->throws(Exception::class, 'The brain map is empty.');

it('executes the command successfully without domains', function (): void {
    config()->set('brain.use_domains', false);
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain/Example');
    Terminal::shouldReceive('cols')->andReturn(71);
    $command = new ShowBrainCommand;

    $mockOutput = Mockery::mock(OutputStyle::class);
    $mockOutput->shouldReceive('writeln')->once();
    $mockOutput->shouldReceive('isVerbose')->andReturn(false);
    $mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);

    $command->setOutput($mockOutput);

    $command->handle();
});

it('executes the command with --processes option', function (): void {
    $this->artisan('brain:show', ['--processes' => true])
        ->assertExitCode(0);
});

it('executes the command with --tasks option', function (): void {
    $this->artisan('brain:show', ['--tasks' => true])
        ->assertExitCode(0);
});

it('executes the command with --queries option', function (): void {
    $this->artisan('brain:show', ['--queries' => true])
        ->assertExitCode(0);
});

it('executes the command with --filter option', function (): void {
    $this->artisan('brain:show', ['--filter' => 'Example'])
        ->assertExitCode(0);
});
