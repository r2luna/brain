# Configuration

Brain is configured via environment variables or by publishing the config file.

## Publishing the Config

```bash
php artisan vendor:publish --provider="Brain\BrainServiceProvider"
```

## Options

### Root Directory

Defines the main directory where workflows, actions, and queries are created.

```bash
BRAIN_ROOT=Brain
```

| Value   | Result                                  |
|---------|-----------------------------------------|
| `Brain` | `app/Brain/Workflows/CreateUser.php`    |
| `Domain`| `app/Domain/Workflows/CreateUser.php`   |
| *(empty)* | `app/Workflows/CreateUser.php`        |

Default: `Brain`

### Use Domains

Organizes code into domain-specific subdirectories.

```bash
BRAIN_USE_DOMAINS=false
```

When enabled:
```
app/Brain/Users/Workflows/CreateUser.php
app/Brain/Users/Actions/RegisterUser.php
app/Brain/Payments/Workflows/ChargeCustomer.php
```

When disabled (default):
```
app/Brain/Workflows/CreateUser.php
app/Brain/Actions/RegisterUser.php
```

### Use Suffix

Automatically appends type suffixes to class names.

```bash
BRAIN_USE_SUFFIX=false
```

When enabled, `CreateUser` becomes `CreateUserWorkflow`, `RegisterUser` becomes `RegisterUserAction`, etc.

### Custom Suffixes

Customize the suffix per type when `use_suffix` is enabled:

```bash
BRAIN_WORKFLOW_SUFFIX=Workflow
BRAIN_ACTION_SUFFIX=Action
BRAIN_QUERY_SUFFIX=Query
```

### Logging

Enables logging for all workflows and actions. See [Events & Logging](/events) for details.

```bash
BRAIN_LOG_ENABLED=false
```

### Broadcasting

Enables real-time broadcasting of workflow and action events. See [Broadcasting](/broadcasting) for details.

```bash
BRAIN_BROADCAST_ENABLED=false
BRAIN_BROADCAST_PROCESSES=true
BRAIN_BROADCAST_TASKS=true
```

| Variable | Description | Default |
|----------|-------------|---------|
| `BRAIN_BROADCAST_ENABLED` | Enable/disable all broadcasting | `false` |
| `BRAIN_BROADCAST_PROCESSES` | Broadcast process start/finish events | `true` |
| `BRAIN_BROADCAST_TASKS` | Broadcast task start/finish events | `true` |

## Full Config File

```php
<?php

return [
    'root'        => env('BRAIN_ROOT', 'Brain'),
    'use_domains' => env('BRAIN_USE_DOMAINS', false),
    'use_suffix'  => env('BRAIN_USE_SUFFIX', false),
    'suffixes'    => [
        'workflow' => env('BRAIN_WORKFLOW_SUFFIX', 'Workflow'),
        'action'  => env('BRAIN_ACTION_SUFFIX', 'Action'),
        'query'   => env('BRAIN_QUERY_SUFFIX', 'Query'),
    ],
    'log'         => env('BRAIN_LOG_ENABLED', false),
    'broadcast'   => [
        'enabled' => env('BRAIN_BROADCAST_ENABLED', false),
        'processes' => env('BRAIN_BROADCAST_PROCESSES', true),
        'tasks' => env('BRAIN_BROADCAST_TASKS', true),
    ],
];
```
