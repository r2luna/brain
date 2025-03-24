<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\Command;

class ShowBrainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brain:show';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Brain Mapping';

    /**
     * Colors that represent the different elements.
     */
    protected array $elemColors = [
        'DOMAIN' => '#6C7280',
        'PROC' => 'blue',
        'TASK' => 'yellow',
        'QERY' => 'green',
    ];

    /**
     * The lines to display.
     */
    private array $lines = [];

    /**
     * The terminal width.
     */
    private ?int $terminalWidth = null;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        (new Printer(
            new BrainMap,
            $this->output
        ))->print();
    }
}
