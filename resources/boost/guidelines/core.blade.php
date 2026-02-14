@php /** @var \Laravel\Boost\Install\GuidelineAssist $assist */ @endphp

## Brain - Process, Task & Query Architecture

Brain (`r2luna/brain`) organizes business logic into three core concepts: **Processes**, **Tasks**, and **Queries**. Use them to keep controllers thin, logic reusable, and side-effects traceable.

| Concept | Purpose | Invocation |
|---------|---------|------------|
| Process | Orchestrates a sequence of tasks | `MyProcess::dispatchSync($payload)` |
| Task    | A single unit of work that mutates state | `MyTask::dispatchSync($payload)` |
| Query   | A read-only operation that returns data | `MyQuery::run($args)` |

### Artisan Commands

- Create a Process: `{{ $assist->artisanCommand('make:process CreateOrder') }}`
- Create a Task: `{{ $assist->artisanCommand('make:task ChargeCustomer') }}`
- Create a Query: `{{ $assist->artisanCommand('make:query GetOrdersByUser') }}`
- Create a Test: `{{ $assist->artisanCommand('make:test CreateOrderTest --stub=process') }}`
- Visualize structure: `{{ $assist->artisanCommand('brain:show') }}`

When `brain.use_domains` is enabled, pass a domain as the second argument:

`{{ $assist->artisanCommand('make:task ChargeCustomer Orders') }}`

---

### Processes

A Process runs a list of tasks in order, wrapping them in a database transaction. Define tasks in the `$tasks` array.

@verbatim
<code-snippet name="Process Example" lang="php">
class CreateOrder extends Process
{
    protected array $tasks = [
        ValidateInventory::class,
        ChargeCustomer::class,
        CreateOrderRecord::class,
        SendConfirmation::class,
    ];
}

// Dispatch synchronously
$result = CreateOrder::dispatchSync(['userId' => 1, 'items' => $items]);
</code-snippet>
@endverbatim

**Adding tasks dynamically:**

@verbatim
<code-snippet name="Dynamic Tasks" lang="php">
$process = new CreateOrder(['userId' => 1]);
$process->addTask(ApplyDiscount::class);
$result = $process->handle();
</code-snippet>
@endverbatim

**Chaining (queue all tasks as a Bus chain):**

@verbatim
<code-snippet name="Chained Process" lang="php">
class ImportData extends Process
{
    protected bool $chain = true;

    protected array $tasks = [
        ParseCsvFile::class,
        ValidateRows::class,
        InsertRecords::class,
    ];
}
</code-snippet>
@endverbatim

**Nesting sub-processes:** Add another Process class to the `$tasks` array. If a sub-process cancels itself, cancellation does not propagate to the parent process.

@verbatim
<code-snippet name="Nested Process" lang="php">
class FulfillOrder extends Process
{
    protected array $tasks = [
        CreateOrder::class,  // This is itself a Process
        NotifyWarehouse::class,
    ];
}
</code-snippet>
@endverbatim

---

### Tasks

A Task is a single unit of work. It receives a payload object and must implement `handle()`. Always return `$this` from `handle()` so the payload flows to the next task in the process.

@verbatim
<code-snippet name="Task Example" lang="php">
/**
 * @property-read int $userId
 * @property-read array $items
 */
class ChargeCustomer extends Task
{
    public function handle(): self
    {
        $user = User::findOrFail($this->userId);

        $charge = $user->charge($this->items);

        $this->chargeId = $charge->id;

        return $this;
    }
}
</code-snippet>
@endverbatim

**Payload:** Tasks access payload properties directly via magic methods (`$this->userId`). Set new properties to pass data to subsequent tasks (`$this->chargeId = $id`). Define expected properties with `@property-read` docblocks — Brain validates that at least one expected key exists at construction time.

**Validation with `rules()`:** Override `rules()` to validate payload using Laravel's Validator before `handle()` runs.

@verbatim
<code-snippet name="Task Validation" lang="php">
/**
 * @property-read string $email
 * @property-read int $age
 */
class RegisterUser extends Task
{
    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'age'   => ['required', 'integer', 'min:18'],
        ];
    }

    public function handle(): self
    {
        // Payload is already validated here
        return $this;
    }
}
</code-snippet>
@endverbatim

**Conditional execution with `runIf()`:** Override `runIf()` to skip the task based on payload data. Skipped tasks fire a `Skipped` event.

@verbatim
<code-snippet name="Conditional Task" lang="php">
class SendWelcomeEmail extends Task
{
    protected function runIf(): bool
    {
        return $this->sendEmail === true;
    }

    public function handle(): self
    {
        // Only runs when sendEmail is true
        return $this;
    }
}
</code-snippet>
@endverbatim

**Delayed execution with `runIn()`:** Override `runIn()` to delay the task by seconds or a Carbon instance.

@verbatim
<code-snippet name="Delayed Task" lang="php">
class SendFollowUp extends Task
{
    protected function runIn(): int
    {
        return 3600; // delay 1 hour
    }

    public function handle(): self
    {
        return $this;
    }
}
</code-snippet>
@endverbatim

**Cancelling the process:** Call `$this->cancelProcess()` inside `handle()` to stop remaining tasks from executing. The process still commits its transaction.

@verbatim
<code-snippet name="Cancel Process" lang="php">
class ValidateInventory extends Task
{
    public function handle(): self
    {
        if (! $this->hasStock) {
            $this->cancelProcess();
        }

        return $this;
    }
}
</code-snippet>
@endverbatim

**Queueable tasks:** Implement `ShouldQueue` to dispatch the task asynchronously when run inside a process.

@verbatim
<code-snippet name="Queueable Task" lang="php">
use Illuminate\Contracts\Queue\ShouldQueue;

class SendConfirmation extends Task implements ShouldQueue
{
    public function handle(): self
    {
        // Runs on the queue
        return $this;
    }
}
</code-snippet>
@endverbatim

**Sensitive properties with `#[Sensitive]`:** Mark payload properties that should be automatically redacted in logs, JSON, and debug output. Sensitive values are wrapped in `SensitiveValue` — accessible inside the task via `$this->key`, but replaced with `**********` everywhere else.

@verbatim
<code-snippet name="Sensitive Task" lang="php">
use Brain\Attributes\Sensitive;

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
        // $this->password returns the real value
        // but logs, JSON, and debug output show "**********"
        return $this;
    }
}
</code-snippet>
@endverbatim

**Process-level sensitive inheritance:** When `#[Sensitive]` is applied to a Process, all child tasks automatically inherit the sensitive keys — even if the tasks don't declare the attribute themselves. Task-level and process-level keys are merged and deduplicated.

@verbatim
<code-snippet name="Sensitive Process" lang="php">
use Brain\Attributes\Sensitive;

#[Sensitive('password', 'credit_card')]
class CreateUserProcess extends Process
{
    protected array $tasks = [
        ValidateInput::class,     // password & credit_card are sensitive here
        ChargeCustomer::class,    // password & credit_card are sensitive here too
        SendConfirmation::class,
    ];
}
</code-snippet>
@endverbatim

---

### Queries

A Query is a read-only class for fetching data. Define constructor parameters for inputs and implement `handle()`.

@verbatim
<code-snippet name="Query Example" lang="php">
class GetOrdersByUser extends Query
{
    public function __construct(
        private int $userId,
        private string $status = 'active',
    ) {}

    public function handle(): Collection
    {
        return Order::query()
            ->where('user_id', $this->userId)
            ->where('status', $this->status)
            ->get();
    }
}

// Usage
$orders = GetOrdersByUser::run(userId: 1, status: 'completed');
</code-snippet>
@endverbatim

---

### Configuration

Brain is configured in `config/brain.php`:

- `root` — Base directory for Brain classes (default: `'Brain'` → `App\Brain\`). Set to `null` for flat structure (`App\Processes`, `App\Tasks`).
- `use_domains` — When `true`, organizes into domain subdirectories: `App\Brain\{Domain}\Processes\`.
- `use_suffix` — When `true`, appends type suffix to class names (e.g., `CreateOrderProcess`).
- `suffixes` — Customize suffix per type: `task`, `process`, `query`.
- `log` — When `true`, logs all process and task events.

---

### Testing Patterns

**Testing a Process:**

@verbatim
<code-snippet name="Process Test" lang="php">
test('create order process runs all tasks', function () {
    $result = CreateOrder::dispatchSync([
        'userId' => 1,
        'items'  => [['id' => 1, 'qty' => 2]],
    ]);

    expect($result->orderId)->not->toBeNull();
});

test('create order process has expected tasks', function () {
    $process = new CreateOrder;

    expect($process->getTasks())->toBe([
        ValidateInventory::class,
        ChargeCustomer::class,
        CreateOrderRecord::class,
        SendConfirmation::class,
    ]);
});
</code-snippet>
@endverbatim

**Testing a Task:**

@verbatim
<code-snippet name="Task Test" lang="php">
test('charge customer task charges the user', function () {
    $user = User::factory()->create();

    $result = ChargeCustomer::dispatchSync([
        'userId' => $user->id,
        'items'  => [['id' => 1, 'qty' => 2]],
    ]);

    expect($result->chargeId)->not->toBeNull();
});
</code-snippet>
@endverbatim

**Testing a Query:**

@verbatim
<code-snippet name="Query Test" lang="php">
test('get orders by user returns matching orders', function () {
    $user = User::factory()->hasOrders(3)->create();

    $result = GetOrdersByUser::run(userId: $user->id);

    expect($result)->toHaveCount(3);
});
</code-snippet>
@endverbatim

---

### Visualization

Use `{{ $assist->artisanCommand('brain:show') }}` to see a map of all processes, tasks, and queries.

- `--processes` (`-p`) — Show only processes and their tasks
- `--tasks` (`-t`) — Show only tasks
- `--queries` (`-Q`) — Show only queries
- `--filter=Name` — Filter by class name

---

### Best Practices

- **Processes wrap tasks in a DB transaction** — tasks that throw will roll back all previous work in the process. Keep side-effects (emails, API calls) in queueable tasks so they run after commit.
- **Payload flows between tasks** — each task receives the payload from the previous one. Set new properties on `$this` to pass data forward.
- **Return `$this` from `handle()`** — this ensures the payload (with any new properties) continues to the next task.
- **Use `@property-read` docblocks** — they document expected payload shape, enable IDE autocompletion, and Brain validates their presence.
- **Queries are for reads, Tasks are for writes** — keep this separation clean. Never mutate state inside a Query.
- **Reuse Tasks across Processes** — tasks are independent units. The same task can appear in multiple processes.
