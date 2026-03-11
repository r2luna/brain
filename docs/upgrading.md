# Upgrading

## Upgrading to v3.0

Version 3.0 renames the core concepts from **Process/Task** to **Workflow/Action** for better clarity.

### Breaking Changes

#### Naming Convention

The primary class names have changed:

| v2.x | v3.0 |
|------|------|
| `Process` | `Workflow` |
| `Task` | `Action` |
| `$tasks` array | `$actions` array |
| `Processes/` directory | `Workflows/` directory |
| `Tasks/` directory | `Actions/` directory |
| `make:process` | `make:workflow` |
| `make:task` | `make:action` |

#### Static `run()` Method

Workflows and Actions now have a static `run()` method that wraps `dispatchSync()`:

```php
// v2.x
CreateUser::dispatchSync($payload);
RegisterUser::dispatchSync($payload);

// v3.0
CreateUser::run($payload);
RegisterUser::run($payload);
```

`dispatchSync()` still works, but `run()` is the recommended way to execute synchronously.

#### Configuration Suffixes

```php
// v2.x
'suffixes' => [
    'process' => 'Process',
    'task'    => 'Task',
],

// v3.0
'suffixes' => [
    'workflow' => 'Workflow',
    'action'  => 'Action',
],
```

The old suffix keys (`process`, `task`) still work but are deprecated.

### Automatic Migration

Use the built-in migration command to automatically update your codebase:

```bash
php artisan brain:migrate --dry-run   # preview changes first
php artisan brain:migrate             # apply the migration
```

This renames directories, files, imports, class references, and namespaces. See [Commands](/commands#migration) for full details.

### Backwards Compatibility

The old `Process` and `Task` base classes still work as deprecated aliases. You can migrate gradually, but we recommend running `brain:migrate` to update everything at once.

---

## Upgrading to v2.0

Version 2.0 introduces new configuration options and enhanced flexibility for organizing your Brain components.

### Breaking Changes

#### Configuration File

The configuration file has been completely restructured. If you've published the config in v1.x, republish it:

```bash
php artisan vendor:publish --provider="Brain\BrainServiceProvider" --force
```

#### Default Behavior

**Domain Organization** — In v1.x, Brain automatically organized components into domain subdirectories. In v2.0, this is **opt-in**:

```php
// v1.x (automatic domains)
app/Brain/Users/Processes/CreateUser.php

// v2.0 default (flat structure)
app/Brain/Processes/CreateUser.php

// v2.0 with BRAIN_USE_DOMAINS=true
app/Brain/Users/Processes/CreateUser.php
```

### Migration Guide

To keep v1.x behavior, add this to your `.env`:

```bash
BRAIN_USE_DOMAINS=true
```

### New Features in v2.0

#### Flexible Root Directory

```bash
BRAIN_ROOT=Domain  # app/Domain instead of app/Brain
```

#### Optional Class Suffixes

```bash
BRAIN_USE_SUFFIX=true
# php artisan make:workflow CreateUser → CreateUserWorkflow.php
```

#### Enhanced Logging

Built-in event system for tracking execution. See [Events & Logging](/events).
