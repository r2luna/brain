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
    | Tests Directory
    |--------------------------------------------------------------------------
    |
    | This value set's the tests directory where BrainShowCommand will look for
    | detect the tests and show them in the output.
    |
    */
    'test_directory' => base_path('tests/Brain/'),

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
    'use_suffix' => true,
];
