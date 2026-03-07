# Getting Started

## Installation

Install Brain via Composer:

```bash
composer require r2luna/brain
```

## Publish Configuration

Optionally publish the configuration file to customize Brain's behavior:

```bash
php artisan vendor:publish --provider="Brain\BrainServiceProvider"
```

This creates `config/brain.php` where you can adjust all settings.

## Create Your First Process

```bash
php artisan make:process CreateUser
```

This generates a Process class at `app/Brain/Processes/CreateUser.php`:

```php
<?php

namespace App\Brain\Processes;

use Brain\Process;

class CreateUser extends Process
{
    protected array $tasks = [
        //
    ];
}
```

## Create Your First Task

```bash
php artisan make:task RegisterUser
```

This generates a Task class at `app/Brain/Tasks/RegisterUser.php`:

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
        // Your business logic here

        return $this;
    }
}
```

## Wire Them Together

Add the task to your process:

```php
class CreateUser extends Process
{
    protected array $tasks = [
        RegisterUser::class,
    ];
}
```

## Dispatch

```php
CreateUser::dispatchSync([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
```

That's it! The process will run `RegisterUser` inside a database transaction. If any task throws an exception, the entire transaction is rolled back.

## Next Steps

- Learn about [Configuration](/configuration) options
- Dive into [Processes](/processes), [Tasks](/tasks), and [Queries](/queries)
- Explore [Queue support](/queues) for async execution
