<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\Command;

/** Console command to display the Brain mapping overview. */
class ShowBrainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brain:show
        {--w|workflows : Show only workflows and their sub-actions}
        {--a|actions : Show only actions}
        {--p|processes : Show only processes (deprecated, use --workflows)}
        {--t|tasks : Show only tasks (deprecated, use --actions)}
        {--Q|queries : Show only queries}
        {--filter= : Filter by class name}
        {--domain= : Filter by domain name}';

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

        if ($this->input?->getOption('workflows') || $this->input?->getOption('processes')) {
            $printer->onlyProcesses();
            $printer->onlyWorkflows();
        }

        if ($this->input?->getOption('actions') || $this->input?->getOption('tasks')) {
            $printer->onlyTasks();
            $printer->onlyActions();
        }

        if ($this->input?->getOption('queries')) {
            $printer->onlyQueries();
        }

        if ($filter = $this->input?->getOption('filter')) {
            $printer->filterBy($filter);
        }

        if ($domain = $this->input?->getOption('domain')) {
            $printer->filterByDomain($domain);
        }

        $printer->print();
    }
}
