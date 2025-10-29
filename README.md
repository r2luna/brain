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

> [!IMPORTANT] 
> Note that every task running inside a process executes within a database transaction by default.

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

#### Validating Task Properties

You can validate the properties passed to a task by defining a `rules()` method that returns an array of validation rules.

```php
/**
 * @property-read User $user
 * @property string $message
 */
class SendWelcomeNotifications extends Task
{
    public function rules(): array
    {
        return [
            'user' => 'required',
            'message' => 'required|string|max:255',
        ];
    }

    public function handle(): self
    {
        // ...

        return $this;
    }
    ...
}
```

Rules will validate based on the payload that was passed to the task when it was dispatched, and will override the default validation based on the docblock @property-read annotations.

#### Task helper methods 

-   `toArray()`: Returns the task properties as an array. Ex. `['user' => $this->user]`

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

## Logging

Brain provides built-in logging functionality to track the execution and outcomes of processes and tasks. By default, logging is disabled but can be enabled through configuration.

### Configuration

To enable logging, set the `BRAIN_LOG_ENABLED` environment variable or update the config file:

```bash
# .env
BRAIN_LOG_ENABLED=true
```

Or publish and modify the configuration file:

```bash
php artisan vendor:publish --provider="Brain\BrainServiceProvider"
```

Then update `config/brain.php`:

```php
'log' => env('BRAIN_LOG_ENABLED', true),
```

### Events

Brain dispatches events throughout the lifecycle of processes and tasks. These events can be used for logging, monitoring, or triggering additional actions.

#### Process Events

-   **`Brain\Processes\Events\Processing`** - Dispatched when a process starts executing
-   **`Brain\Processes\Events\Processed`** - Dispatched when a process completes successfully
-   **`Brain\Processes\Events\Error`** - Dispatched when a process encounters an error

Each event contains:
-   `process` (string): The process class name
-   `runProcessId` (string): A unique ID for this execution
-   `payload` (array|object): The data passed to the process
-   `meta` (array): Additional metadata

#### Task Events

-   **`Brain\Tasks\Events\Processing`** - Dispatched when a task starts executing
-   **`Brain\Tasks\Events\Processed`** - Dispatched when a task completes successfully
-   **`Brain\Tasks\Events\Cancelled`** - Dispatched when a task is cancelled via `cancelProcess()`
-   **`Brain\Tasks\Events\Skipped`** - Dispatched when a task is skipped (when `runIf()` returns false)
-   **`Brain\Tasks\Events\Error`** - Dispatched when a task encounters an error

Each event contains:
-   `task` (string): The task class name
-   `payload` (array|object|null): The data passed to the task
-   `process` (string|null): The process class name (if running within a process)
-   `runProcessId` (string|null): The process execution ID (if running within a process)
-   `meta` (array): Additional metadata

### Custom Event Listeners

You can create custom event listeners to handle these events:

```php
// app/Providers/EventServiceProvider.php

use Brain\Processes\Events\Processed as ProcessProcessed;
use Brain\Tasks\Events\Error as TaskError;

protected $listen = [
    ProcessProcessed::class => [
        NotifyAdminOfProcessCompletion::class,
    ],
    TaskError::class => [
        LogTaskErrorToExternalService::class,
    ],
];
```

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
