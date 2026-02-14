# Queries

A **Query** is a read-only class for fetching data. Constructor parameters define inputs, and `handle()` returns the result.

## Creating a Query

```bash
php artisan make:query GetUserByEmail
```

You can optionally specify a model:

```bash
php artisan make:query GetUserByEmail
# When prompted, enter: User
```

## Basic Structure

```php
<?php

namespace App\Brain\Queries;

use App\Models\User;
use Brain\Query;

class GetUserByEmail extends Query
{
    public function __construct(
        private readonly string $email,
    ) {}

    public function handle(): ?User
    {
        return User::where('email', $this->email)->first();
    }
}
```

## Invocation

Queries are invoked using the static `run()` method with named arguments:

```php
$user = GetUserByEmail::run(email: 'john@example.com');
```

## Examples

### With Multiple Parameters

```php
class GetOrders extends Query
{
    public function __construct(
        private readonly int $userId,
        private readonly string $status = 'completed',
        private readonly int $limit = 10,
    ) {}

    public function handle(): Collection
    {
        return Order::where('user_id', $this->userId)
            ->where('status', $this->status)
            ->limit($this->limit)
            ->get();
    }
}

// Usage
$orders = GetOrders::run(userId: 42);
$orders = GetOrders::run(userId: 42, status: 'pending', limit: 5);
```

### Returning Aggregates

```php
class GetMonthlyRevenue extends Query
{
    public function __construct(
        private readonly int $year,
        private readonly int $month,
    ) {}

    public function handle(): float
    {
        return Payment::whereYear('created_at', $this->year)
            ->whereMonth('created_at', $this->month)
            ->sum('amount');
    }
}

$revenue = GetMonthlyRevenue::run(year: 2025, month: 11);
```
