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

it('should set length to 0 when brain map is empty', function () {
    $this->map->map = collect([]);
    $this->printerReflection->run('getLengthOfTheLongestDomain');
    expect($this->printerReflection->get('lengthLongestDomain'))->toBe(0);
});

it('should set correct length of longest domain', function () {
    $this->map->map = collect([
        ['domain' => 'short.com'],
        ['domain' => 'verylongdomain.com'],
        ['domain' => 'medium.com'],
    ]);

    $this->printerReflection->run('getLengthOfTheLongestDomain');
    expect($this->printerReflection->get('lengthLongestDomain'))->toBe(18);
});

it('should handle null domain values', function () {
    $this->map->map = collect([
        ['domain' => null],
        ['domain' => 'example.com'],
    ]);

    $this->printerReflection->run('getLengthOfTheLongestDomain');
    expect($this->printerReflection->get('lengthLongestDomain'))->toBe(11);
});

it('should check if if creating all the correct lines to be printed', function () {
    Terminal::shouldReceive('cols')->andReturn(71);
    $this->printerReflection->run('getTerminalWidth');
    $this->printerReflection->run('createLines');

    $lines = $this->printerReflection->get('lines');

    expect($lines)->toBe([
        ['  <fg=#6C7280;options=bold>EXAMPLE</>   <fg=blue;options=bold>PROC</>  <fg=white;options=bold>ExampleProcess</><fg=#6C7280> ....................................</>'],
        [''],
        ['  <fg=#6C7280;options=bold>EXAMPLE2</>  <fg=blue;options=bold>PROC</>  <fg=white;options=bold>ExampleProcess2</><fg=#6C7280> ........................... chained</>'],
        [''],
    ]);
});

it('should not add new line when last line is already empty', function () {
    $this->printerReflection->set('lines', [
        ['Some content'],
        [''],
    ]);

    $this->printerReflection->run('addNewLine');

    expect($this->printerReflection->get('lines'))->toBe([
        ['Some content'],
        [''],
    ]);
});

it('should add new line when last line is not empty', function () {
    $this->printerReflection->set('lines', [
        ['Some content'],
    ]);

    $this->printerReflection->run('addNewLine');

    expect($this->printerReflection->get('lines'))->toBe([
        ['Some content'],
        [''],
    ]);
});
