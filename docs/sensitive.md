# Sensitive Data

Use the `#[Sensitive]` attribute to mark payload properties that should be automatically redacted in logs, JSON serialization, and debug output.

## Usage

```php
use Brain\Attributes\Sensitive;
use Brain\Task;

/**
 * @property-read string $email
 * @property string $password
 * @property string $credit_card
 */
#[Sensitive('password', 'credit_card')]
class CreateUser extends Task
{
    public function handle(): self
    {
        // $this->password returns the real value inside handle()
        // but logs, JSON, and debug output show "**********"
        return $this;
    }
}
```

## How It Works

Sensitive values are internally wrapped in a `SensitiveValue` object:

- **Inside the task** — `$this->password` returns the real value transparently
- **In logs** — Replaced with `**********`
- **In JSON** — Replaced with `**********`
- **In debug output** — Replaced with `**********`
- **In `brain:show -vv`** — Shows a `[sensitive]` indicator

## Process-Level Inheritance

When `#[Sensitive]` is applied to a Process, **all child tasks** automatically inherit the sensitive keys — even if the tasks themselves don't declare the attribute:

```php
use Brain\Attributes\Sensitive;
use Brain\Process;

#[Sensitive('password', 'credit_card')]
class CreateUser extends Process
{
    protected array $tasks = [
        ValidateInput::class,     // password & credit_card redacted
        ChargeCustomer::class,    // password & credit_card redacted
        SendConfirmation::class,  // password & credit_card redacted
    ];
}
```

Task-level and process-level keys are merged and deduplicated. A task can define additional sensitive keys beyond what the process specifies:

```php
#[Sensitive('password')]
class CreateUser extends Process
{
    protected array $tasks = [
        ChargeCustomer::class, // has #[Sensitive('cvv')] → both password and cvv are redacted
    ];
}
```

::: tip
The `brain:show -vv` command displays a `[sensitive]` indicator next to sensitive properties, making it easy to verify your configuration.
:::
