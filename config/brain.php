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
    | If you set this to null, we will use a *flat* structure within the app directory.
    | Meaning that everything will be created directly inside the app folder.
    | Ex: App\Processes, App\Tasks, App\Queries
    |
    */
    'root' => env('BRAIN_ROOT', 'Brain'),

    /*
    |--------------------------------------------------------------------------
    | Use Domains
    |--------------------------------------------------------------------------
    |
    | When enabled (true), this setting organizes processes, tasks, and queries
    | into domain-specific subdirectories within the main Brain directory.
    | This helps in maintaining a clear structure, especially in larger
    | applications with multiple domains.
    |
    */
    'use_domains' => env('BRAIN_USE_DOMAINS', false),

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

    /*
    |--------------------------------------------------------------------------
    | Logging System
    |--------------------------------------------------------------------------
    |
    | When enabled (true), this setting activates logging for all processes,
    | tasks, and queries, allowing you to track their execution and outcomes.
    |
    */
    'log' => env('BRAIN_LOG_ENABLED', false),
];
