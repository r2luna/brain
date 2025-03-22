<?php

declare(strict_types=1);

namespace Brain\Console;

use Brain\Facades\Terminal;
use Exception;
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
     * @var int The length of the longest domain in the brain's map.
     */
    private int $lengthLongestDomain = 0;

    /**
     * @var array The lines to be printed to the terminal.
     */
    private array $lines = [];

    public function __construct(
        private readonly BrainMap $brain
    ) {
        $this->checkIfBrainMapIsEmpty();
        $this->getTerminalWidth();
        $this->getLengthOfTheLongestDomain();
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

    private function checkIfBrainMapIsEmpty(): void
    {
        if (empty($this->brain->map)) {
            throw new Exception('The brain map is empty.');
        }
    }

    private function createLines(): void
    {
        $this->brain->map->each(function ($domainData) {

            /** Example
             * 1. $domainSpaces
             * 2. Type
             * 3. ClassName
             * 4. Dots
             * 5. Mixed Properties like: chained, queued
             | 1    |2   |  3                | 5                   | 6   |
              USER   PROC   CreateUserProcess ............................
                     TASK   CreateUser ...................................
                     TASK   WelcomeNofication ..................... queued
             */
            $domain = data_get($domainData, 'domain');
            $domainSpaces = $this->getDomainSpaces($domain);

            $this->addProcessesLine($domainData, $domain, $domainSpaces);
            // $this->addTasksLines($process, $domain, $domainSpaces);

            $this->addNewLine();
        });
    }

    /**
     * Adds a formatted line for each process in the given domain data to the output lines.
     *
     * This method iterates over the processes in the provided domain data and constructs
     * a formatted string for each process. The string includes the domain name, process name,
     * and additional metadata such as whether the process is part of a chain. The formatted
     * string is styled with terminal colors and added to the `$lines` property.
     *
     * @param  array  $domainData  An associative array containing domain data, including a 'processes' key.
     *                             Each process should have a 'name' key and a 'chain' key.
     * @param  string  $currentDomain  The name of the current domain being processed.
     * @param  string  $spaces  A string of spaces used for alignment in the output.
     */
    private function addProcessesLine(array $domainData, string $currentDomain, string $spaces): void
    {
        foreach ($domainData['processes'] as $process) {
            $processName = data_get($process, 'name');
            $inChain = $process['chain'] ? ' chained' : '.';
            $dots = str_repeat('.', max($this->terminalWidth - mb_strlen($currentDomain.$processName.$spaces.$inChain.'PROC  ') - 5, 0));
            $dots = $dots === '' || $dots === '0' ? $dots : " $dots";

            $this->lines[] = [
                sprintf(
                    '  <fg=%s;options=bold>%s</>%s<fg=%s;options=bold>%s</>  <fg=%s;options=bold>%s</><fg=#6C7280>%s%s</>',
                    $this->elemColors['DOMAIN'],
                    strtoupper($currentDomain),
                    $spaces,
                    $this->elemColors['PROC'],
                    'PROC',
                    'white',
                    $processName,
                    $dots,
                    $inChain
                ),
            ];
        }
    }

    /**
     * Generates a string of spaces to align domain names for console output.
     *
     * This method calculates the number of spaces needed to align a given domain
     * name based on the length of the longest domain name and returns a string
     * containing that many spaces.
     *
     * @param  string  $domain  The domain name for which to calculate the spacing.
     * @return string A string of spaces for alignment.
     */
    private function getDomainSpaces(string $domain): string
    {
        return str_repeat(' ', max($this->lengthLongestDomain + 2 - mb_strlen((string) $domain), 0));
    }

    /**
     * Adds a new empty line to the lines array if the last line is not already empty.
     * This ensures that there is a separation or spacing between lines when needed.
     */
    private function addNewLine(): void
    {
        if (end($this->lines) === ['']) {
            return;
        }

        $this->lines[] = [''];
    }

    /**
     * Calculates the length of the longest domain in the brain's map.
     *
     * This method iterates through the brain's map, sorts the entries
     * in descending order based on the length of the 'domain' value,
     * and retrieves the length of the longest domain.
     */
    private function getLengthOfTheLongestDomain(): void
    {
        $this->lengthLongestDomain = mb_strlen(
            (string) data_get($this->brain->map
                ->sortByDesc(fn ($value): int => mb_strlen((string) $value['domain']))
                ->first(), 'domain')
        );
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
