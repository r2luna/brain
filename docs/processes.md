# Processes

A **Process** orchestrates a sequence of tasks, running them in order within a database transaction.

## Creating a Process

```bash
php artisan make:process CreateUser
```

## Basic Structure

```php
<?php

namespace App\Brain\Processes;

use Brain\Process;

class CreateUser extends Process
{
    protected array $tasks = [
        RegisterUser::class,
        SendWelcomeEmail::class,
        NotifyStaff::class,
    ];
}
```

## Dispatching

```php
// Synchronous (within a transaction)
CreateUser::dispatchSync([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);

// Asynchronous (queued)
CreateUser::dispatch([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
```

## Transaction Behavior

Every task inside a process executes within a database transaction by default. If any task throws an exception, the entire transaction is rolled back.

::: warning
Queued tasks run outside the process transaction scope. Only synchronous tasks are wrapped in the transaction.
:::

## Dynamic Tasks

You can add tasks dynamically using `addTask()`:

```php
class CreateUser extends Process
{
    protected array $tasks = [
        RegisterUser::class,
    ];

    public function __construct(array $payload = [])
    {
        parent::__construct($payload);

        if ($payload['send_welcome'] ?? false) {
            $this->addTask(SendWelcomeEmail::class);
        }
    }
}
```

## Sub-Processes

Processes can be nested by adding a Process class to the `$tasks` array:

```php
class OnboardUser extends Process
{
    protected array $tasks = [
        CreateUser::class,          // another Process
        SetupBilling::class,        // a Task
        SendOnboardingEmail::class, // a Task
    ];
}
```

## Chaining (Queued)

When `$chain = true`, queued tasks are dispatched as a Laravel Bus chain instead of being dispatched individually:

```php
class SyncData extends Process
{
    protected bool $chain = true;

    protected array $tasks = [
        FetchExternalData::class,  // ShouldQueue
        TransformData::class,      // ShouldQueue
        SaveData::class,           // ShouldQueue
    ];
}
```
