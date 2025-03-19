<?php

declare(strict_types=1);

namespace Brain\Console;

use Brain\Facades\Terminal;

class Printer
{
    private int $terminalWidth;

    public function __construct(
        private readonly BrainMap $map
    ) {
        //
    }

    private function getTerminalWidth(): void
    {
        $this->terminalWidth = Terminal::cols();
    }
}
