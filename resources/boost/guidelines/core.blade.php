@php /** @var \Laravel\Boost\Install\GuidelineAssist $assist */ @endphp

## Brain - Workflow, Action & Query Architecture

Brain (`r2luna/brain`) organizes business logic into three core concepts: **Workflows**, **Actions**, and **Queries**. Use them to keep controllers thin, logic reusable, and side-effects traceable.

| Concept  | Purpose | Invocation |
|----------|---------|------------|
| Workflow | Orchestrates a sequence of actions | `MyWorkflow::run($payload)` |
| Action   | A single unit of work that mutates state | `MyAction::run($payload)` |
| Query    | A read-only operation that returns data | `MyQuery::run($args)` |

### Artisan Commands

- Create a Workflow: `{{ $assist->artisanCommand('make:workflow CreateOrder') }}`
- Create an Action: `{{ $assist->artisanCommand('make:action ChargeCustomer') }}`
- Create a Query: `{{ $assist->artisanCommand('make:query GetOrdersByUser') }}`
- Create a Test: `{{ $assist->artisanCommand('make:test CreateOrderTest --stub=workflow') }}`
- Visualize structure: `{{ $assist->artisanCommand('brain:show') }}`
- Run interactively: `{{ $assist->artisanCommand('brain:run') }}`
- Rerun a previous execution: `{{ $assist->artisanCommand('brain:run --rerun') }}`

When `brain.use_domains` is enabled, pass a domain as the second argument:

`{{ $assist->artisanCommand('make:action ChargeCustomer Orders') }}`

---

### Workflows

A Workflow runs a list of actions in order, wrapping them in a database transaction. Define actions in the `$actions` array.

@verbatim
<code-snippet name="Workflow Example" lang="php">
class CreateOrder extends Workflow
{
    protected array $actions = [
        ValidateInventory::class,
        ChargeCustomer::class,
        CreateOrderRecord::class,
        SendConfirmation::class,
    ];
}

// Run synchronously
$result = CreateOrder::run(['userId' => 1, 'items' => $items]);
</code-snippet>
@endverbatim

**Adding actions dynamically:**

@verbatim
<code-snippet name="Dynamic Actions" lang="php">
$workflow = new CreateOrder(['userId' => 1]);
$workflow->addAction(ApplyDiscount::class);
$result = $workflow->handle();
</code-snippet>
@endverbatim

**Chaining (queue all actions as a Bus chain):**

@verbatim
<code-snippet name="Chained Workflow" lang="php">
class ImportData extends Workflow
{
    protected bool $chain = true;

    protected array $actions = [
        ParseCsvFile::class,
        ValidateRows::class,
        InsertRecords::class,
    ];
}
</code-snippet>
@endverbatim

**Nesting sub-workflows:** Add another Workflow class to the `$actions` array. If a sub-workflow cancels itself, cancellation does not propagate to the parent workflow.

@verbatim
<code-snippet name="Nested Workflow" lang="php">
class FulfillOrder extends Workflow
{
    protected array $actions = [
        CreateOrder::class,  // This is itself a Workflow
        NotifyWarehouse::class,
    ];
}
</code-snippet>
@endverbatim

---

### Actions

An Action is a single unit of work. It receives a payload object and must implement `handle()`. Always return `$this` from `handle()` so the payload flows to the next action in the workflow.

@verbatim
<code-snippet name="Action Example" lang="php">
/**
 * @property-read int $userId
 * @property-read array $items
 */
class ChargeCustomer extends Action
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

**Payload:** Actions access payload properties directly via magic methods (`$this->userId`). Set new properties to pass data to subsequent actions (`$this->chargeId = $id`). Define expected properties with `@property-read` docblocks — Brain validates that at least one expected key exists at construction time.

**Validation with `rules()`:** Override `rules()` to validate payload using Laravel's Validator before `handle()` runs.

@verbatim
<code-snippet name="Action Validation" lang="php">
/**
 * @property-read string $email
 * @property-read int $age
 */
class RegisterUser extends Action
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

**Conditional execution with `runIf()`:** Override `runIf()` to skip the action based on payload data. Skipped actions fire a `Skipped` event.

@verbatim
<code-snippet name="Conditional Action" lang="php">
class SendWelcomeEmail extends Action
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

**Delayed execution with `runIn()`:** Override `runIn()` to delay the action by seconds or a Carbon instance.

@verbatim
<code-snippet name="Delayed Action" lang="php">
class SendFollowUp extends Action
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

**Cancelling the workflow:** Call `$this->cancelWorkflow()` inside `handle()` to stop remaining actions from executing. The workflow still commits its transaction.

@verbatim
<code-snippet name="Cancel Workflow" lang="php">
class ValidateInventory extends Action
{
    public function handle(): self
    {
        if (! $this->hasStock) {
            $this->cancelWorkflow();
        }

        return $this;
    }
}
</code-snippet>
@endverbatim

**Queueable actions:** Implement `ShouldQueue` to dispatch the action asynchronously when run inside a workflow.

@verbatim
<code-snippet name="Queueable Action" lang="php">
use Illuminate\Contracts\Queue\ShouldQueue;

class SendConfirmation extends Action implements ShouldQueue
{
    public function handle(): self
    {
        // Runs on the queue
        return $this;
    }
}
</code-snippet>
@endverbatim

**Sensitive properties with `#[Sensitive]`:** Mark payload properties that should be automatically redacted in logs, JSON, and debug output. Sensitive values are wrapped in `SensitiveValue` — accessible inside the action via `$this->key`, but replaced with `**********` everywhere else.

@verbatim
<code-snippet name="Sensitive Action" lang="php">
use Brain\Attributes\Sensitive;

/**
 * @property-read string $email
 * @property string $password
 * @property string $credit_card
 */
#[Sensitive('password', 'credit_card')]
class CreateUser extends Action
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

**Workflow-level sensitive inheritance:** When `#[Sensitive]` is applied to a Workflow, all child actions automatically inherit the sensitive keys — even if the actions don't declare the attribute themselves. Action-level and workflow-level keys are merged and deduplicated.

@verbatim
<code-snippet name="Sensitive Workflow" lang="php">
use Brain\Attributes\Sensitive;

#[Sensitive('password', 'credit_card')]
class CreateUserWorkflow extends Workflow
{
    protected array $actions = [
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

- `root` — Base directory for Brain classes (default: `'Brain'` → `App\Brain\`). Set to `null` for flat structure (`App\Workflows`, `App\Actions`).
- `use_domains` — When `true`, organizes into domain subdirectories: `App\Brain\{Domain}\Workflows\`.
- `use_suffix` — When `true`, appends type suffix to class names (e.g., `CreateOrderWorkflow`).
- `suffixes` — Customize suffix per type: `workflow`, `action`, `query`.
- `log` — When `true`, logs all workflow and action events.

---

### Testing Patterns

**Testing a Workflow:**

@verbatim
<code-snippet name="Workflow Test" lang="php">
test('create order workflow runs all actions', function () {
    $result = CreateOrder::run([
        'userId' => 1,
        'items'  => [['id' => 1, 'qty' => 2]],
    ]);

    expect($result->orderId)->not->toBeNull();
});

test('create order workflow has expected actions', function () {
    $workflow = new CreateOrder;

    expect($workflow->getActions())->toBe([
        ValidateInventory::class,
        ChargeCustomer::class,
        CreateOrderRecord::class,
        SendConfirmation::class,
    ]);
});
</code-snippet>
@endverbatim

**Testing an Action:**

@verbatim
<code-snippet name="Action Test" lang="php">
test('charge customer action charges the user', function () {
    $user = User::factory()->create();

    $result = ChargeCustomer::run([
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

Use `{{ $assist->artisanCommand('brain:show') }}` to see a map of all workflows, actions, and queries.

- `--workflows` (`-w`) — Show only workflows and their actions
- `--actions` (`-a`) — Show only actions
- `--queries` (`-Q`) — Show only queries
- `--filter=Name` — Filter by class name

---

### Running Interactively

Use `{{ $assist->artisanCommand('brain:run') }}` to interactively select and execute a Workflow or Action from the terminal. The command walks you through selecting a target, choosing sync or async dispatch, filling payload properties, previewing, and executing.

Every successful run is saved to history (`storage/brain/run-history.json`, max 50 entries). Use `{{ $assist->artisanCommand('brain:run --rerun') }}` to replay a previous execution with the same parameters.

---

### Best Practices

- **Workflows wrap actions in a DB transaction** — actions that throw will roll back all previous work in the workflow. Keep side-effects (emails, API calls) in queueable actions so they run after commit.
- **Payload flows between actions** — each action receives the payload from the previous one. Set new properties on `$this` to pass data forward.
- **Return `$this` from `handle()`** — this ensures the payload (with any new properties) continues to the next action.
- **Use `@property-read` docblocks** — they document expected payload shape, enable IDE autocompletion, and Brain validates their presence.
- **Queries are for reads, Actions are for writes** — keep this separation clean. Never mutate state inside a Query.
- **Reuse Actions across Workflows** — actions are independent units. The same action can appear in multiple workflows.
