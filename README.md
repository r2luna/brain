<p align="center">
    <h1 align="center">Brain</h1>
    <p align="center">
        <a href="https://github.com/r2luna/brain/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/r2luna/brain/main.yml?branch=main"></a>
        <a href="https://packagist.org/packages/r2luna/brain"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/r2luna/brain"></a>
        <a href="https://packagist.org/packages/r2luna/brain"><img alt="Latest Version" src="https://img.shields.io/packagist/v/r2luna/brain"></a>
        <a href="https://packagist.org/packages/r2luna/brain"><img alt="License" src="https://img.shields.io/packagist/l/r2luna/brain"></a>
    </p>
</p>

---

**Brain** is an elegant PHP package that helps you organize your Laravel application using Domain-Driven Design principles through a simple command-line interface.

## Features

- 🎯 **Domain-Driven Structure**: Easily create new domains with proper architecture
- 🔄 **Process Management**: Generate process classes for complex business operations
- 🔍 **Query Objects**: Create dedicated query classes for database operations
- ⚡ **Task Management**: Generate task classes for background jobs and queue operations

## Installation

You can install the package via composer:

```bash
composer require r2luna/brain
```

## Usage

### Creating a Process

```bash
php artisan make:process CreateUserProcess --domain=Users
```

This will create a new process class in `app/Brain/Users/Processes/CreateUserProcess.php`

### Creating a Query

```bash
php artisan make:query GetUserByEmailQuery --domain=Users
```

This will create a new query class in `app/Brain/Users/Queries/GetUserByEmailQuery.php`

### Creating a Task

```bash
php artisan make:task SendWelcomeEmailTask --domain=Users
```

This will create a new task class in `app/Brain/Users/Tasks/SendWelcomeEmailTask.php`

## Example Usage

```php
// Using a Query
$user = GetUserByEmailQuery::run('john@example.com');

// Using a Process
CreateUserProcess::dispatch([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Using a Task
SendWelcomeEmailTask::dispatch($user);
```

## Architecture

Brain helps you organize your code into three main concepts:

- **Processes**: Complex business operations that might involve multiple steps
- **Queries**: Database queries and data retrieval operations
- **Tasks**: Background jobs and queue operations

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

- [Rafael Lunardelli](https://github.com/r2luna)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
