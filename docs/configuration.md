# Configuration

Brain is configured via environment variables or by publishing the config file.

## Publishing the Config

```bash
php artisan vendor:publish --provider="Brain\BrainServiceProvider"
```

## Options

### Root Directory

Defines the main directory where processes, tasks, and queries are created.

```bash
BRAIN_ROOT=Brain
```

| Value   | Result                                  |
|---------|-----------------------------------------|
| `Brain` | `app/Brain/Processes/CreateUser.php`    |
| `Domain`| `app/Domain/Processes/CreateUser.php`   |
| *(empty)* | `app/Processes/CreateUser.php`        |

Default: `Brain`

### Use Domains

Organizes code into domain-specific subdirectories.

```bash
BRAIN_USE_DOMAINS=false
```

When enabled:
```
app/Brain/Users/Processes/CreateUser.php
app/Brain/Users/Tasks/RegisterUser.php
app/Brain/Payments/Processes/ChargeCustomer.php
```

When disabled (default):
```
app/Brain/Processes/CreateUser.php
app/Brain/Tasks/RegisterUser.php
```

### Use Suffix

Automatically appends type suffixes to class names.

```bash
BRAIN_USE_SUFFIX=false
```

When enabled, `CreateUser` becomes `CreateUserProcess`, `RegisterUser` becomes `RegisterUserTask`, etc.

### Custom Suffixes

Customize the suffix per type when `use_suffix` is enabled:

```bash
BRAIN_TASK_SUFFIX=Task
BRAIN_PROCESS_SUFFIX=Process
BRAIN_QUERY_SUFFIX=Query
```

### Logging

Enables logging for all processes and tasks. See [Events & Logging](/events) for details.

```bash
BRAIN_LOG_ENABLED=false
```

## Full Config File

```php
<?php

return [
    'root'        => env('BRAIN_ROOT', 'Brain'),
    'use_domains' => env('BRAIN_USE_DOMAINS', false),
    'use_suffix'  => env('BRAIN_USE_SUFFIX', false),
    'suffixes'    => [
        'task'    => env('BRAIN_TASK_SUFFIX', 'Task'),
        'process' => env('BRAIN_PROCESS_SUFFIX', 'Process'),
        'query'   => env('BRAIN_QUERY_SUFFIX', 'Query'),
    ],
    'log'         => env('BRAIN_LOG_ENABLED', false),
];
```
