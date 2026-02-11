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
    protected $signature = 'brain:show
        {--p|processes : Show only processes and their sub-tasks}
        {--t|tasks : Show only tasks}
        {--Q|queries : Show only queries}
        {--filter= : Filter by class name}';

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
        $printer = new Printer(
            new BrainMap,
            $this->output,
        );

        if ($this->input?->getOption('processes')) {
            $printer->onlyProcesses();
        }

        if ($this->input?->getOption('tasks')) {
            $printer->onlyTasks();
        }

        if ($this->input?->getOption('queries')) {
            $printer->onlyQueries();
        }

        if ($filter = $this->input?->getOption('filter')) {
            $printer->filterBy($filter);
        }

        $printer->print();
    }
}
