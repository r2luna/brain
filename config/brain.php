<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Root Directory
    |--------------------------------------------------------------------------
    |
    | This value sets the main directory where all processes, tasks, and
    | queries will be created. If you set this to null, a flat structure
    | will be used within the app directory, meaning everything will be
    | created directly inside the app folder (App\Processes, App\Tasks).
    |
    */

    'root' => env('BRAIN_ROOT', 'Brain'),

    /*
    |--------------------------------------------------------------------------
    | Use Domains
    |--------------------------------------------------------------------------
    |
    | When enabled, this setting organizes processes, tasks, and queries
    | into domain-specific subdirectories within the main Brain directory.
    | This helps in maintaining a clear structure, especially in larger
    | applications with multiple domains.
    |
    */

    'use_domains' => env('BRAIN_USE_DOMAINS', false),

    /*
    |--------------------------------------------------------------------------
    | Class Name Suffix
    |--------------------------------------------------------------------------
    |
    | When enabled, this setting appends a suffix to class names based on
    | their type: "Task" for tasks, "Process" for processes, and "Query"
    | for queries. You may customize each suffix individually below.
    |
    */

    'use_suffix' => env('BRAIN_USE_SUFFIX', false),

    /*
    |--------------------------------------------------------------------------
    | Suffix Names
    |--------------------------------------------------------------------------
    |
    | Here you may customize the suffix appended to each class type when
    | the "use_suffix" option is enabled. These values are used by the
    | make commands to generate class names with the correct suffix.
    |
    */

    'suffixes' => [
        'task' => env('BRAIN_TASK_SUFFIX', 'Task'),
        'process' => env('BRAIN_PROCESS_SUFFIX', 'Process'),
        'query' => env('BRAIN_QUERY_SUFFIX', 'Query'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, this setting activates logging for all processes,
    | tasks, and queries, allowing you to track their execution and
    | outcomes throughout your application.
    |
    */

    'log' => env('BRAIN_LOG_ENABLED', false),

];
