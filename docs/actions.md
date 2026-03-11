# Actions

An **Action** is a single unit of work that receives a payload and implements a `handle()` method.

## Creating an Action

```bash
php artisan make:action RegisterUser
```

## Basic Structure

```php
<?php

namespace App\Brain\Actions;

use Brain\Action;

/**
 * @property-read string $name
 * @property-read string $email
 */
class RegisterUser extends Action
{
    public function handle(): self
    {
        $user = User::create([
            'name'  => $this->name,
            'email' => $this->email,
        ]);

        // Pass data to subsequent actions
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

### Passing Data Between Actions

Set new properties on the action to make them available to subsequent actions in a workflow:

```php
class RegisterUser extends Action
{
    public function handle(): self
    {
        $user = User::create([...]);
        $this->userId = $user->id; // available in next action
        return $this;
    }
}

class SendWelcomeEmail extends Action
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

Actions can be run independently, outside of a workflow:

```php
RegisterUser::run([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
```

## Conditional Execution

Use `runIf()` to conditionally skip an action within a workflow:

```php
/**
 * @property-read int $amount
 */
class ApplyDiscount extends Action
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

When `runIf()` returns `false`, the action is skipped and a `Skipped` event is dispatched.

## Validation

Define validation rules for the payload using the `rules()` method:

```php
/**
 * @property-read User $user
 * @property string $message
 */
class SendNotification extends Action
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

## Cancel Workflow

From inside an action, you can cancel the entire workflow:

```php
class CheckEligibility extends Action
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
`cancelProcess()` does not work with queued actions — only synchronous actions within a workflow.
:::

## Helper Methods

- `toArray()` — Returns the action payload as an array.

```php
$action = RegisterUser::run(['name' => 'John', 'email' => 'john@example.com']);
$action->toArray(); // ['name' => 'John', 'email' => 'john@example.com']
```
