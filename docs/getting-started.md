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

## Create Your First Workflow

```bash
php artisan make:workflow CreateUser
```

This generates a Workflow class at `app/Brain/Workflows/CreateUser.php`:

```php
<?php

namespace App\Brain\Workflows;

use Brain\Workflow;

class CreateUser extends Workflow
{
    protected array $actions = [
        //
    ];
}
```

## Create Your First Action

```bash
php artisan make:action RegisterUser
```

This generates an Action class at `app/Brain/Actions/RegisterUser.php`:

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
        // Your business logic here

        return $this;
    }
}
```

## Wire Them Together

Add the action to your workflow:

```php
class CreateUser extends Workflow
{
    protected array $actions = [
        RegisterUser::class,
    ];
}
```

## Run

```php
CreateUser::run([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
```

That's it! The workflow will run `RegisterUser` inside a database transaction. If any action throws an exception, the entire transaction is rolled back.

## Next Steps

- Learn about [Configuration](/configuration) options
- Dive into [Workflows](/workflows), [Actions](/actions), and [Queries](/queries)
- Explore [Queue support](/queues) for async execution
