<?php

declare(strict_types=1);

use Brain\Console\BrainMap;
use Brain\Console\Printer;
use Brain\Facades\Terminal;
use Tests\Feature\Fixtures\PrinterReflection;

beforeEach(function () {
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain');
    $this->map = new BrainMap;
    $this->printer = new Printer($this->map);
    $this->printerReflection = new PrinterReflection($this->printer);
});

it('should load the current terminal width', function () {
    Terminal::shouldReceive('cols')->andReturn(200);

    $this->printerReflection->run('getTerminalWidth');

    expect($this->printerReflection->get('terminalWidth'))->toBe(200);
});

it('should print lines to the terminal', function () {
    $mockOutput = Mockery::mock(Illuminate\Console\OutputStyle::class);

    $this->printerReflection->set('output', $mockOutput);

    $this->printerReflection->set('lines', [
        ['Line 1', 'Line 2'],
        ['Line 3'],
    ]);

    $mockOutput->shouldReceive('writeln')->once();

    $this->printer->print();
});

it('should return 0 when brain map is empty', function () {
    $this->map->map = collect([]);
    expect($this->printerReflection->run('getLengthOfTheLongestDomain'))->toBe(0);
});

it('should return correct length of longest domain', function () {
    $this->map->map = collect([
        ['domain' => 'short.com'],
        ['domain' => 'verylongdomain.com'],
        ['domain' => 'medium.com'],
    ]);

    expect($this->printerReflection->run('getLengthOfTheLongestDomain'))->toBe(18);
});

it('should handle null domain values', function () {
    $this->map->map = collect([
        ['domain' => null],
        ['domain' => 'example.com'],
    ]);

    expect($this->printerReflection->run('getLengthOfTheLongestDomain'))->toBe(11);
});
