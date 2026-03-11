# Workflows

A **Workflow** orchestrates a sequence of actions, running them in order within a database transaction.

## Creating a Workflow

```bash
php artisan make:workflow CreateUser
```

## Basic Structure

```php
<?php

namespace App\Brain\Workflows;

use Brain\Workflow;

class CreateUser extends Workflow
{
    protected array $actions = [
        RegisterUser::class,
        SendWelcomeEmail::class,
        NotifyStaff::class,
    ];
}
```

## Running

```php
// Synchronous (within a transaction)
CreateUser::run([
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

Every action inside a workflow executes within a database transaction by default. If any action throws an exception, the entire transaction is rolled back.

::: warning
Queued actions run outside the workflow transaction scope. Only synchronous actions are wrapped in the transaction.
:::

## Dynamic Actions

You can add actions dynamically using `addAction()`:

```php
class CreateUser extends Workflow
{
    protected array $actions = [
        RegisterUser::class,
    ];

    public function __construct(array $payload = [])
    {
        parent::__construct($payload);

        if ($payload['send_welcome'] ?? false) {
            $this->addAction(SendWelcomeEmail::class);
        }
    }
}
```

## Sub-Workflows

Workflows can be nested by adding a Workflow class to the `$actions` array:

```php
class OnboardUser extends Workflow
{
    protected array $actions = [
        CreateUser::class,          // another Workflow
        SetupBilling::class,        // an Action
        SendOnboardingEmail::class, // an Action
    ];
}
```

## Chaining (Queued)

When `$chain = true`, queued actions are dispatched as a Laravel Bus chain instead of being dispatched individually:

```php
class SyncData extends Workflow
{
    protected bool $chain = true;

    protected array $actions = [
        FetchExternalData::class,  // ShouldQueue
        TransformData::class,      // ShouldQueue
        SaveData::class,           // ShouldQueue
    ];
}
```
