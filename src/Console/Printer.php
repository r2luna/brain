<?php

declare(strict_types=1);

namespace Brain\Console;

use Brain\Facades\Terminal;
use Illuminate\Console\Concerns\InteractsWithIO;

class Printer
{
    use InteractsWithIO;

    /**
     * Colors that represent the different elements.
     */
    private array $elemColors = [
        'DOMAIN' => '#6C7280',
        'PROC' => 'blue',
        'TASK' => 'yellow',
        'QERY' => 'green',
    ];

    /**
     * @var int The width of the terminal in characters.
     */
    private int $terminalWidth;

    /**
     * @var array The lines to be printed to the terminal.
     */
    private array $lines = [];

    public function __construct(
        private readonly BrainMap $map
    ) {
        $this->getTerminalWidth();
    }

    /**
     * Prints the collected lines to the output.
     *
     * This method uses the `collect` helper to create a collection
     * from the `$lines` property, flattens the collection, and writes
     * the resulting output using the `$output`'s `writeln` method.
     */
    public function print(): void
    {
        $flattenedLines = [];
        array_walk_recursive($this->lines, function ($line) use (&$flattenedLines) {
            $flattenedLines[] = $line;
        });

        $this->output->writeln($flattenedLines);
    }

    /**
     * Retrieves and sets the terminal's current width by using the Terminal utility.
     *
     * This method assigns the number of columns (width) of the terminal
     * to the `$terminalWidth` property.
     */
    private function getTerminalWidth(): void
    {
        $this->terminalWidth = Terminal::cols();
    }
}
