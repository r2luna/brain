![Banner](https://github.com/user-attachments/assets/35c6a96d-bebb-40c5-b20b-5bce9a915fe0)

<p align="center">
    <a href="https://github.com/r2luna/brain/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/r2luna/brain/tests.yml"></a>
    <a href="https://packagist.org/packages/r2luna/brain"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/r2luna/brain"></a>
    <a href="https://packagist.org/packages/r2luna/brain"><img alt="Latest Version" src="https://img.shields.io/packagist/v/r2luna/brain"></a>
    <a href="https://packagist.org/packages/r2luna/brain"><img alt="License" src="https://img.shields.io/packagist/l/r2luna/brain"></a>
</p>

---

**Brain** is an elegant Laravel Package that helps you organize your Laravel application using Domain-Driven Design principles through a simple command-line interface.

## Features

-   🎯 **Domain-Driven Structure**: Easily create new domains with proper architecture
-   🔄 **Process Management**: Generate process classes for complex business operations
-   🔍 **Query Objects**: Create dedicated query classes for database operations
-   ⚡ **Task Management**: Generate task classes for background jobs and queue operations

## Gains

-   ♻️ **Code Reusability**: By using tasks, you can easily reuse code across different processes, reducing duplication and enhancing maintainability.
-   🧩 **Clear Domain Understanding**: The structured approach provides a better understanding of each domain's processes, making it easier to manage and scale your application.
-   🔧 **Improved Maintainability**: With well-defined domains and processes, maintaining and updating your application becomes more straightforward and less error-prone.

## Installation

You can install the package via composer:

```bash
composer require r2luna/brain
```

## Usage

### Creating a Process

```bash
php artisan make:process
... follow prompt
name: CreateUserProcess
domain: Users
```

This will create a new process class in `app/Brain/Users/Processes/CreateUserProcess.php`

> [!IMPORTANT] Note that every task running inside a process executes within a database transaction by default.

### Creating a Task

```bash
php artisan make:task
... follow prompt
name: SendWelcomeEmailTask
domain: Users
```

This will create a new task class in `app/Brain/Users/Tasks/SendWelcomeEmailTask.php`

#### Queuable Tasks

To send the task to the queue simply implements Laravel Contract `ShouldQueue` to the class

```php
<?php

namespace App\Brain\User;

use Brain\Task;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotifications extends Task implements ShouldQueue
{
    public function handle(): self
    {
        //

        return $this;
    }
}
```

#### Delay Queueable Tasks

Brain Tasks has a protected function called `runIn()` that you can use to determine when do you want to run the job if you need to delay.

```php
class SendWelcomeNotifications extends Task implements ShouldQueue
{
    protected function runIn(): int|Carbon|null
    {
        return now()->addDays(2);
    }
    ...
}
```

#### Conditional Run Tasks in a Process

You can use a protected function `runIf()` to conditionally run a task.

```php
/**
 * @property-read int $amount
 */
class SendWelcomeNotifications extends Task
{
    protected function runIf(): bool
    {
        return $this->amount > 200;
    }
    ...
}
```

#### Cancel the Process

If you need, for any reason, cancel the process from inside a task. You can call `cancelProcess()` method to do it.

```php
class AddRoles extends Task
{
    public function handle(): self
    {
        if($anyReason) {
            $this->cancelProcess();
        }

        return $this;
    }
}
```

> [!CAUTION]
> This will not work if the task is setup to run in a queue.

### Creating a Query

```bash
php artisan make:query
... follow prompt
name: GetUserByEmailQuery
domain: Users
model: User
```

This will create a new query class in `app/Brain/Users/Queries/GetUserByEmailQuery.php`

## Example Usage

```php
// Using a Query
$user = GetUserByEmailQuery::run('john@example.com');

// Setting up a Process
class CreateUserProcess extends Process
{
    protected array $tasks = [
        RegisterUserTask::class,
        SendWelcomeEmailTask::class, // Async task
        NotifyStaffTask::class, // Async task
        SubProcess::class
    ];
}

// Using a Process
CreateUserProcess::dispatch([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Using a Task without a process
SendWelcomeEmailTask::dispatch([
    'user' => $user
]);
```

## Architecture

Brain helps you organize your code into three main concepts:

-   **Processes**: Complex business operations that might involve multiple steps
-   **Queries**: Database queries and data retrieval operations
-   **Tasks**: Sync/Async operations that can be called as part of a process or not

Each concept is organized within its respective domain, promoting clean architecture and separation of concerns.

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email rafael@lunardelli.me instead of using the issue tracker.

## Credits

-   [Rafael Lunardelli](https://github.com/r2luna)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
