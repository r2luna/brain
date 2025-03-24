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
     * Execute the console command.
     */
    public function handle(): void
    {
        (new Printer(new BrainMap, $this->output))->print();
    }
}
