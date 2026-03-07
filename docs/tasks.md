# Tasks

A **Task** is a single unit of work that receives a payload and implements a `handle()` method.

## Creating a Task

```bash
php artisan make:task RegisterUser
```

## Basic Structure

```php
<?php

namespace App\Brain\Tasks;

use Brain\Task;

/**
 * @property-read string $name
 * @property-read string $email
 */
class RegisterUser extends Task
{
    public function handle(): self
    {
        $user = User::create([
            'name'  => $this->name,
            'email' => $this->email,
        ]);

        // Pass data to subsequent tasks
        $this->userId = $user->id;

        return $this;
    }
}
```

## Payload

### Accessing Properties

Payload properties are accessed via magic methods:

```php
$this->name;   // reads from payload
$this->email;  // reads from payload
```

### Documenting Properties

Use `@property-read` for input properties and `@property` for output properties:

```php
/**
 * @property-read string $name       ← input (required)
 * @property-read string $email      ← input (required)
 * @property int $userId             ← output (set during handle)
 */
```

Brain validates that at least one expected key exists in the payload at construction.

### Passing Data Between Tasks

Set new properties on the task to make them available to subsequent tasks in a process:

```php
class RegisterUser extends Task
{
    public function handle(): self
    {
        $user = User::create([...]);
        $this->userId = $user->id; // available in next task
        return $this;
    }
}

class SendWelcomeEmail extends Task
{
    public function handle(): self
    {
        $userId = $this->userId; // set by RegisterUser
        // ...
        return $this;
    }
}
```

## Standalone Usage

Tasks can be dispatched independently, outside of a process:

```php
RegisterUser::dispatchSync([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
```

## Conditional Execution

Use `runIf()` to conditionally skip a task within a process:

```php
/**
 * @property-read int $amount
 */
class ApplyDiscount extends Task
{
    protected function runIf(): bool
    {
        return $this->amount > 200;
    }

    public function handle(): self
    {
        // only runs if amount > 200
        return $this;
    }
}
```

When `runIf()` returns `false`, the task is skipped and a `Skipped` event is dispatched.

## Validation

Define validation rules for the payload using the `rules()` method:

```php
/**
 * @property-read User $user
 * @property string $message
 */
class SendNotification extends Task
{
    public function rules(): array
    {
        return [
            'user'    => 'required',
            'message' => 'required|string|max:255',
        ];
    }

    public function handle(): self
    {
        // ...
        return $this;
    }
}
```

Rules override the default validation based on `@property-read` annotations.

## Cancel Process

From inside a task, you can cancel the entire process:

```php
class CheckEligibility extends Task
{
    public function handle(): self
    {
        if ($this->user->isBanned()) {
            $this->cancelProcess();
        }

        return $this;
    }
}
```

::: warning
`cancelProcess()` does not work with queued tasks — only synchronous tasks within a process.
:::

## Helper Methods

- `toArray()` — Returns the task payload as an array.

```php
$task = RegisterUser::dispatchSync(['name' => 'John', 'email' => 'john@example.com']);
$task->toArray(); // ['name' => 'John', 'email' => 'john@example.com']
```
