<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Root Directory
    |--------------------------------------------------------------------------
    |
    | This value set's the main directory where we will create all the processes
    | tasks, and queries.
    |
    */
    'root' => app_path('Brain'),

    /*
    |--------------------------------------------------------------------------
    | Suffix for Task, Process, and Query
    |--------------------------------------------------------------------------
    |
    | When enabled (true), this setting appends a suffix to class names based
    | on their type:
    | - "Task" for tasks
    | - "Process" for processes
    | - "Query" for queries
    |
    */
    'use_suffix' => env('BRAIN_USE_SUFFIX', false),

    'suffixes' => [
        'task' => env('BRAIN_TASK_SUFFIX', 'Task'),
        'process' => env('BRAIN_PROCESS_SUFFIX', 'Process'),
        'query' => env('BRAIN_QUERY_SUFFIX', 'Query'),
    ],
];
