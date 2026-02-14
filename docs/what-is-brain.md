# What is Brain?

**Brain** is a Laravel package that helps you organize your application's business logic using three core building blocks: **Processes**, **Tasks**, and **Queries**.

Instead of scattering business logic across controllers, models, and services, Brain gives you a clear, consistent structure that scales with your application.

## Core Concepts

| Concept   | Purpose                                       | Invocation                            |
|-----------|-----------------------------------------------|---------------------------------------|
| Process   | Orchestrates a sequence of tasks in a transaction | `MyProcess::dispatchSync($payload)` |
| Task      | A single unit of work that mutates state       | `MyTask::dispatchSync($payload)`    |
| Query     | A read-only operation that returns data        | `MyQuery::run($args)`               |

## Why Brain?

- **Code Reusability** — Tasks can be shared across different processes, reducing duplication.
- **Clear Domain Understanding** — A structured approach makes it easy to understand what each domain does.
- **Improved Maintainability** — Well-defined boundaries make updates straightforward.
- **Built-in Safeguards** — Database transactions, validation, conditional execution, and sensitive data redaction out of the box.

## Quick Example

```php
// Define a process with its tasks
class CreateUserProcess extends Process
{
    protected array $tasks = [
        RegisterUser::class,
        SendWelcomeEmail::class,
        NotifyStaff::class,
    ];
}

// Dispatch it
CreateUserProcess::dispatchSync([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);

// Or use a query
$user = GetUserByEmail::run('john@example.com');
```

## Requirements

- PHP 8.3+
- Laravel 11.37+ or 12.0+
