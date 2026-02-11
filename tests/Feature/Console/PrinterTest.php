<?php

declare(strict_types=1);

use Brain\Console\BrainMap;
use Brain\Console\Printer;
use Brain\Facades\Terminal;
use Illuminate\Console\OutputStyle;
use Tests\Feature\Fixtures\PrinterReflection;

beforeEach(function (): void {
    config()->set('brain.use_domains', true);
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain');
    Terminal::shouldReceive('cols')->andReturn(71);

    $this->mockOutput = Mockery::mock(OutputStyle::class);

    $this->map = new BrainMap;
    $this->printer = new Printer($this->map);
    $this->printerReflection = new PrinterReflection($this->printer);
    $this->printerReflection->set('output', $this->mockOutput);
});

it('should load the current terminal width', function (): void {
    $this->printerReflection->run('getTerminalWidth');

    expect($this->printerReflection->get('terminalWidth'))->toBe(71);
});

it('should print lines to the terminal', function (): void {
    $this->printerReflection->set('lines', [
        ['Line 1', 'Line 2'],
        ['Line 3'],
    ]);

    $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
    $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
    $this->mockOutput->shouldReceive('writeln')->once();

    $this->printer->print();
});

it('should check if creating all the correct lines to be printed', function (): void {
    $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
    $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
    $this->printerReflection->run('getTerminalWidth');
    $this->printerReflection->run('run');
    $lines = $this->printerReflection->get('lines');

    expect($lines)->toBe([
        ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 44).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 47).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 39).' queued</>'],
        ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
        [''],
        ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess2</><fg=#6C7280> '.str_repeat('·', 35).' chained</>'],
        ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
        [''],
    ]);
});

it('should not add new line when last line is already empty', function (): void {
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

it('should add new line when last line is not empty', function (): void {
    $this->printerReflection->set('lines', [
        ['Some content'],
    ]);

    $this->printerReflection->run('addNewLine');

    expect($this->printerReflection->get('lines'))->toBe([
        ['Some content'],
        [''],
    ]);
});

it('should throw exception if brain is empty', function (): void {
    config()->set('brain.root', __DIR__.'/../Fixtures/EmptyBrain');

    $map = new BrainMap;
    $printer = new Printer($map);
    $printer->print();
})->throws(Exception::class, 'The brain map is empty.');

it('should set output style correctly', function (): void {
    $mockOutput = Mockery::mock(OutputStyle::class);

    $this->printer->setOutput($mockOutput);

    expect($this->printerReflection->get('output'))->toBe($mockOutput);
});

it('should allow overriding existing output style', function (): void {
    $mockOutput1 = Mockery::mock(OutputStyle::class);
    $mockOutput2 = Mockery::mock(OutputStyle::class);

    $this->printer->setOutput($mockOutput1);
    $this->printer->setOutput($mockOutput2);

    expect($this->printerReflection->get('output'))->toBe($mockOutput2);
});

// --------------------
// -v Verbose

it('should print tasks and processes of a process when using -v', function (): void {
    $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
    $this->mockOutput->shouldReceive('isVerbose')->andReturn(true);
    $this->printerReflection->run('getTerminalWidth');
    $this->printerReflection->run('run');
    $lines = $this->printerReflection->get('lines');

    expect($lines)->toBe([
        ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 44).'</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 24).' queued</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 47).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 39).' queued</>'],
        ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
        [''],
        ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess2</><fg=#6C7280> '.str_repeat('·', 35).' chained</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>├── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 24).' queued</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>└── </><fg=white>2. </><fg=blue;options=bold>P</> <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 29).'</>'],
        ['  <fg=#6C7280>│   </>                      <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 14).' queued</>'],
        ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
        [''],
    ]);
});

it('should print task properties of a process when using -vv', function (): void {
    $this->mockOutput->shouldReceive('isVerbose')->andReturn(true);
    $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(true);
    $this->printerReflection->run('getTerminalWidth');
    $this->printerReflection->run('run');
    $lines = $this->printerReflection->get('lines');

    expect($lines)->toBe([
        ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 44).'</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 24).' queued</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 47).'</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>→ email</><fg=#6C7280>: string</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>→ paymentId</><fg=#6C7280>: int</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>→ email</><fg=#6C7280>: string</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>→ paymentId</><fg=#6C7280>: int</>'],
        ['  <fg=#6C7280>│   </>               <fg=white>← id</><fg=#6C7280>: int</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 39).' queued</>'],
        ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
        [''],
        ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess2</><fg=#6C7280> '.str_repeat('·', 35).' chained</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>├── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 24).' queued</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>└── </><fg=white>2. </><fg=blue;options=bold>P</> <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 29).'</>'],
        ['  <fg=#6C7280>│   </>                      <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 14).' queued</>'],
        ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
        [''],
    ]);
});

// --------------------
// Without domains

// --------------------
// Filtering

describe('filtering', function (): void {
    it('should only show processes when onlyProcesses is set', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printer->onlyProcesses();
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
            ['  <fg=#6C7280>└── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 44).'</>'],
            [''],
            ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
            ['  <fg=#6C7280>└── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess2</><fg=#6C7280> '.str_repeat('·', 35).' chained</>'],
            [''],
        ]);
    });

    it('should only show tasks when onlyTasks is set', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printer->onlyTasks();
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
            ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 47).'</>'],
            ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
            ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
            ['  <fg=#6C7280>└── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 39).' queued</>'],
            [''],
        ]);
    });

    it('should only show queries when onlyQueries is set', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printer->onlyQueries();
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
            ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
            [''],
            ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
            ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
            [''],
        ]);
    });

    it('should filter items by name', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printer->filterBy('ExampleQuery');
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
            ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
            [''],
            ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
            ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 46).'</>'],
            [''],
        ]);
    });

    it('should show process with matching sub-tasks when filter matches sub-task name', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printer->onlyProcesses();
        $this->printer->filterBy('ExampleTask4');
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>EXAMPLE</>'],
            ['  <fg=#6C7280>└── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 44).'</>'],
            ['  <fg=#6C7280>    </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 24).' queued</>'],
            [''],
            ['  <fg=#6C7280;options=bold>EXAMPLE2</>'],
            ['  <fg=#6C7280>└── </><fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess2</><fg=#6C7280> '.str_repeat('·', 35).' chained</>'],
            ['  <fg=#6C7280>    </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 24).' queued</>'],
            [''],
        ]);
    });
});

// --------------------
// Without domains

describe('without domains configuration', function (): void {
    beforeEach(function (): void {
        config()->set('brain.use_domains', false);
        config()->set('brain.root', __DIR__.'/../Fixtures/Brain/Example');
        Terminal::shouldReceive('cols')->andReturn(71);

        $this->mockOutput = Mockery::mock(OutputStyle::class);

        $this->map = new BrainMap;
        $this->printer = new Printer($this->map);
        $this->printerReflection = new PrinterReflection($this->printer);
        $this->printerReflection->set('output', $this->mockOutput);
    });

    it('should check if creating all the correct lines without domains', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['<fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 50).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 53).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 45).' queued</>'],
            ['<fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 52).'</>'],
            [''],
        ]);
    });

    it('should print tasks and processes of a process when using -v without domains', function (): void {
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(true);
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['<fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 50).'</>'],
            ['      <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 36).' queued</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 53).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 45).' queued</>'],
            ['<fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 52).'</>'],
            [''],
        ]);
    });

    it('should print task properties of a process when using -vv without domains', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(true);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(true);
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toEqual([
            ['<fg=blue;options=bold>PROC</>  <fg=white>ExampleProcess</><fg=#6C7280> '.str_repeat('·', 50).'</>'],
            ['      <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>T</> <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 36).' queued</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask</><fg=#6C7280> '.str_repeat('·', 53).'</>'],
            ['         <fg=#A3BE8C>→ email</><fg=#6C7280>: string</>'],
            ['         <fg=#A3BE8C>→ paymentId</><fg=#6C7280>: int</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['         <fg=#A3BE8C>→ email</><fg=#6C7280>: string</>'],
            ['         <fg=#A3BE8C>→ paymentId</><fg=#6C7280>: int</>'],
            ['         <fg=white>← id</><fg=#6C7280>: int</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 45).' queued</>'],
            ['<fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 52).'</>'],
            [''],
        ]);
    });
});
