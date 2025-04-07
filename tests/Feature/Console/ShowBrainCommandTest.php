<?php

declare(strict_types=1);

use Brain\Console\ShowBrainCommand;
use Brain\Facades\Terminal;
use Illuminate\Console\OutputStyle;

beforeEach(function (): void {
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

    $this->command->setOutput($mockOutput);

    $this->command->handle();
});

it('throws exception when brain is empty', function (): void {
    config()->set('brain.root', __DIR__.'/../Fixtures/EmptyBrain');

    $this->command->handle();
})->throws(Exception::class, 'The brain map is empty.');
