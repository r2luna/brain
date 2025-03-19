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
