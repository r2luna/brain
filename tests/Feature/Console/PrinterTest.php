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
        ['  <fg=#6C7280;options=bold>EXAMPLE3</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>FLOW</>  <fg=white>ExampleWorkflow</><fg=#6C7280> '.str_repeat('·', 43).'</>'],
        ['  <fg=#6C7280>└── </><fg=yellow;options=bold>ACTN</>  <fg=white>ExampleAction</><fg=#6C7280> '.str_repeat('·', 45).'</>'],
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
        ['  <fg=#6C7280;options=bold>EXAMPLE3</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>FLOW</>  <fg=white>ExampleWorkflow</><fg=#6C7280> '.str_repeat('·', 43).'</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>A</> <fg=white>ExampleAction</><fg=#6C7280> '.str_repeat('·', 30).'</>'],
        ['  <fg=#6C7280>└── </><fg=yellow;options=bold>ACTN</>  <fg=white>ExampleAction</><fg=#6C7280> '.str_repeat('·', 45).'</>'],
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
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>← paymentId</><fg=#6C7280>: int</>'],
        ['  <fg=#6C7280>├── </><fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 46).'</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'],
        ['  <fg=#6C7280>│   </>               <fg=#A3BE8C>← paymentId</><fg=#6C7280>: int</>'],
        ['  <fg=#6C7280>│   </>               <fg=white>→ id</><fg=#6C7280>: int</>'],
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
        ['  <fg=#6C7280;options=bold>EXAMPLE3</>'],
        ['  <fg=#6C7280>├── </><fg=blue;options=bold>FLOW</>  <fg=white>ExampleWorkflow</><fg=#6C7280> '.str_repeat('·', 43).'</>'],
        ['  <fg=#6C7280>│   </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>A</> <fg=white>ExampleAction</><fg=#6C7280> '.str_repeat('·', 30).'</>'],
        ['  <fg=#6C7280>│   </>                   <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'],
        ['  <fg=#6C7280>│   </>                   <fg=#A3BE8C>← paymentId</><fg=#6C7280>: int</>'],
        ['  <fg=#6C7280>└── </><fg=yellow;options=bold>ACTN</>  <fg=white>ExampleAction</><fg=#6C7280> '.str_repeat('·', 45).'</>'],
        ['  <fg=#6C7280>    </>               <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'],
        ['  <fg=#6C7280>    </>               <fg=#A3BE8C>← paymentId</><fg=#6C7280>: int</>'],
        [''],
    ]);
});

// --------------------
// -vv Sensitive properties

it('should show [sensitive] indicator for sensitive properties in -vv mode', function (): void {
    $this->mockOutput->shouldReceive('isVerbose')->andReturn(true);
    $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(true);

    $task = [
        'name' => 'SensitiveTask',
        'properties' => [
            ['name' => 'email', 'type' => 'string', 'direction' => 'output', 'sensitive' => false],
            ['name' => 'password', 'type' => 'string', 'direction' => 'input', 'sensitive' => true],
        ],
    ];

    $this->printerReflection->run('addProperties', [$task, '', 3]);
    $lines = $this->printerReflection->get('lines');

    expect($lines[0])->toBe(['   <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'])
        ->and($lines[1])->toBe(['   <fg=white>→ password</><fg=#6C7280>: string</> <fg=red>[sensitive]</>']);
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
            ['         <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'],
            ['         <fg=#A3BE8C>← paymentId</><fg=#6C7280>: int</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask2</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['         <fg=#A3BE8C>← email</><fg=#6C7280>: string</>'],
            ['         <fg=#A3BE8C>← paymentId</><fg=#6C7280>: int</>'],
            ['         <fg=white>→ id</><fg=#6C7280>: int</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask3</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            ['<fg=yellow;options=bold>TASK</>  <fg=white>ExampleTask4</><fg=#6C7280> '.str_repeat('·', 45).' queued</>'],
            ['<fg=green;options=bold>QERY</>  <fg=white>ExampleQuery</> <fg=#6C7280>'.str_repeat('·', 52).'</>'],
            [''],
        ]);
    });
});

describe('subdirectory grouping', function (): void {
    it('should group items by subdirectory with domain headers', function (): void {
        config()->set('brain.use_domains', false);
        Terminal::shouldReceive('cols')->andReturn(71);

        $mockOutput = Mockery::mock(OutputStyle::class);
        $mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);

        $map = new BrainMap;
        // Override map with items that have groups
        $map->map = collect([
            'Root' => [
                'path' => '/fake',
                'workflows' => [
                    ['name' => 'RootWorkflow', 'fullName' => 'App\\RootWorkflow', 'chain' => false, 'onQueue' => null, 'group' => null, 'properties' => [], 'tasks' => []],
                ],
                'actions' => [
                    ['name' => 'AuthAction', 'fullName' => 'App\\Auth\\AuthAction', 'queue' => false, 'onQueue' => null, 'type' => 'action', 'group' => 'Auth', 'properties' => []],
                    ['name' => 'AuthAction2', 'fullName' => 'App\\Auth\\AuthAction2', 'queue' => false, 'onQueue' => null, 'type' => 'action', 'group' => 'Auth', 'properties' => []],
                ],
                'processes' => [],
                'tasks' => [],
                'queries' => [
                    ['name' => 'AuthQuery', 'fullName' => 'App\\Auth\\AuthQuery', 'group' => 'Auth', 'properties' => []],
                ],
            ],
        ]);

        $printer = new Printer($map);
        $printerReflection = new PrinterReflection($printer);
        $printerReflection->set('output', $mockOutput);
        $printerReflection->run('getTerminalWidth');
        $printerReflection->run('run');
        $lines = $printerReflection->get('lines');

        expect($lines)->toBe([
            ['<fg=blue;options=bold>FLOW</>  <fg=white>RootWorkflow</><fg=#6C7280> '.str_repeat('·', 52).'</>'],
            [''],
            ['<fg=#6C7280;options=bold>AUTH</>'],
            ['  <fg=#6C7280>├── </><fg=yellow;options=bold>ACTN</>  <fg=white>AuthAction</><fg=#6C7280> '.str_repeat('·', 48).'</>'],
            ['  <fg=#6C7280>├── </><fg=yellow;options=bold>ACTN</>  <fg=white>AuthAction2</><fg=#6C7280> '.str_repeat('·', 47).'</>'],
            ['  <fg=#6C7280>└── </><fg=green;options=bold>QERY</>  <fg=white>AuthQuery</> <fg=#6C7280>'.str_repeat('·', 49).'</>'],
            [''],
        ]);
    });

    it('should render multiple groups with separator between them', function (): void {
        config()->set('brain.use_domains', false);
        Terminal::shouldReceive('cols')->andReturn(71);

        $mockOutput = Mockery::mock(OutputStyle::class);
        $mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);

        $map = new BrainMap;
        $map->map = collect([
            'Root' => [
                'path' => '/fake',
                'workflows' => [],
                'actions' => [
                    ['name' => 'AuthAction', 'fullName' => 'App\\AuthAction', 'queue' => false, 'onQueue' => null, 'type' => 'action', 'group' => 'Auth', 'properties' => []],
                    ['name' => 'PayAction', 'fullName' => 'App\\PayAction', 'queue' => false, 'onQueue' => null, 'type' => 'action', 'group' => 'Payment', 'properties' => []],
                ],
                'processes' => [],
                'tasks' => [],
                'queries' => [],
            ],
        ]);

        $printer = new Printer($map);
        $printerReflection = new PrinterReflection($printer);
        $printerReflection->set('output', $mockOutput);
        $printerReflection->run('getTerminalWidth');
        $printerReflection->run('run');
        $lines = $printerReflection->get('lines');

        expect($lines)->toBe([
            ['<fg=#6C7280;options=bold>AUTH</>'],
            ['  <fg=#6C7280>└── </><fg=yellow;options=bold>ACTN</>  <fg=white>AuthAction</><fg=#6C7280> '.str_repeat('·', 48).'</>'],
            [''],
            ['<fg=#6C7280;options=bold>PAYMENT</>'],
            ['  <fg=#6C7280>└── </><fg=yellow;options=bold>ACTN</>  <fg=white>PayAction</><fg=#6C7280> '.str_repeat('·', 49).'</>'],
            [''],
        ]);
    });

    it('should show workflow with matching sub-actions when filter matches action name', function (): void {
        $this->mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $this->mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);
        $this->printer->onlyWorkflows();
        $this->printer->filterBy('ExampleAction');
        $this->printerReflection->run('getTerminalWidth');
        $this->printerReflection->run('run');
        $lines = $this->printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>EXAMPLE3</>'],
            ['  <fg=#6C7280>└── </><fg=blue;options=bold>FLOW</>  <fg=white>ExampleWorkflow</><fg=#6C7280> '.str_repeat('·', 43).'</>'],
            ['  <fg=#6C7280>    </>            <fg=#6C7280>└── </><fg=white>1. </><fg=yellow;options=bold>A</> <fg=white>ExampleAction</><fg=#6C7280> '.str_repeat('·', 30).'</>'],
            [''],
        ]);
    });

    it('should group items by subdirectory with domains enabled', function (): void {
        config()->set('brain.use_domains', true);
        Terminal::shouldReceive('cols')->andReturn(71);

        $mockOutput = Mockery::mock(OutputStyle::class);
        $mockOutput->shouldReceive('isVerbose')->andReturn(false);
        $mockOutput->shouldReceive('isVeryVerbose')->andReturn(false);

        $map = new BrainMap;
        $map->map = collect([
            'MyDomain' => [
                'domain' => 'MyDomain',
                'path' => '/fake',
                'workflows' => [],
                'actions' => [
                    ['name' => 'PayAction', 'fullName' => 'App\\Pay\\PayAction', 'queue' => false, 'onQueue' => null, 'type' => 'action', 'group' => 'Payment', 'properties' => []],
                ],
                'processes' => [],
                'tasks' => [],
                'queries' => [],
            ],
        ]);

        $printer = new Printer($map);
        $printerReflection = new PrinterReflection($printer);
        $printerReflection->set('output', $mockOutput);
        $printerReflection->run('getTerminalWidth');
        $printerReflection->run('run');
        $lines = $printerReflection->get('lines');

        expect($lines)->toBe([
            ['  <fg=#6C7280;options=bold>MYDOMAIN</>'],
            ['  <fg=#6C7280;options=bold>PAYMENT</>'],
            ['  <fg=#6C7280>└── </><fg=yellow;options=bold>ACTN</>  <fg=white>PayAction</><fg=#6C7280> '.str_repeat('·', 49).'</>'],
            [''],
        ]);
    });
});
