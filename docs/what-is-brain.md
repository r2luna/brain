# What is Brain?

**Brain** is a Laravel package that helps you organize your application's business logic using three core building blocks: **Workflows**, **Actions**, and **Queries**.

Instead of scattering business logic across controllers, models, and services, Brain gives you a clear, consistent structure that scales with your application.

## Core Concepts

| Concept   | Purpose                                       | Invocation                            |
|-----------|-----------------------------------------------|---------------------------------------|
| Workflow  | Orchestrates a sequence of actions in a transaction | `MyWorkflow::run($payload)` |
| Action    | A single unit of work that mutates state       | `MyAction::run($payload)`    |
| Query     | A read-only operation that returns data        | `MyQuery::run($args)`               |

## Why Brain?

- **Code Reusability** — Actions can be shared across different workflows, reducing duplication.
- **Clear Domain Understanding** — A structured approach makes it easy to understand what each domain does.
- **Improved Maintainability** — Well-defined boundaries make updates straightforward.
- **Built-in Safeguards** — Database transactions, validation, conditional execution, and sensitive data redaction out of the box.

## Quick Example

```php
// Define a workflow with its actions
class CreateUser extends Workflow
{
    protected array $actions = [
        RegisterUser::class,
        SendWelcomeEmail::class,
        NotifyStaff::class,
    ];
}

// Run it
CreateUser::run([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);

// Or use a query
$user = GetUserByEmail::run('john@example.com');
```

## Requirements

- PHP 8.3+
- Laravel 11.37+ or 12.0+
