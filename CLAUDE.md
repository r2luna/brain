# Brain

Brain (`r2luna/brain`) is a Laravel package that organizes business logic into three core concepts: **Workflows**, **Actions**, and **Queries**.

## Project Structure

```
src/
├── Workflow.php         # Base Workflow class
├── Action.php           # Base Action class
├── Query.php            # Base Query class
├── Process.php          # (deprecated) Alias for Workflow
├── Task.php             # (deprecated) Alias for Action
├── BrainServiceProvider.php
├── Attributes/          # PHP attributes (e.g., Sensitive, OnQueue)
├── Console/             # Artisan commands (make:workflow, make:action, make:query, brain:show, brain:run)
├── Exceptions/          # InvalidPayload, etc.
├── Facades/
├── Workflows/           # Workflow infrastructure (Events, Listeners)
├── Actions/             # Action infrastructure (Events, Listeners, Middleware)
├── Processes/           # (deprecated) Process infrastructure
├── Tasks/               # (deprecated) Task infrastructure
├── Queries/             # Query infrastructure (Events, Listeners)
└── Tests/               # Test stubs
tests/
├── Feature/             # Feature tests (Pest)
│   ├── Fixtures/        # Test fixture classes
│   ├── Console/         # Command tests
│   └── *Test.php        # Workflow, Action, Query, etc.
├── TestCase.php         # Base test case (Orchestra Testbench)
└── Pest.php
```

## Core Concepts

| Concept  | Purpose | Base Class | Invocation |
|----------|---------|------------|------------|
| Workflow | Orchestrates a sequence of actions in a DB transaction | `Brain\Workflow` | `MyWorkflow::run($payload)` |
| Action   | A single unit of work that mutates state | `Brain\Action` | `MyAction::run($payload)` |
| Process  | *(deprecated)* Alias for Workflow | `Brain\Process` | `MyProcess::run($payload)` |
| Task     | *(deprecated)* Alias for Action | `Brain\Task` | `MyTask::run($payload)` |
| Query    | A read-only operation that returns data | `Brain\Query` | `MyQuery::run($args)` |

### Workflows

- Run a list of actions in order, wrapped in a database transaction.
- Actions are defined in the `$actions` array.
- Support dynamic action addition via `addAction()`.
- Support chaining (queued actions as a Bus chain) via `$chain = true`.
- Sub-workflows can be nested by adding a Workflow class to `$actions`.
- Run synchronously via `MyWorkflow::run($payload)` (wraps `dispatchSync`).
- Dispatch to queue via `MyWorkflow::dispatch($payload)`.

### Actions

- Receive a payload object and implement `handle()`, returning `$this`.
- Payload properties are accessed via magic methods (`$this->userId`).
- New properties can be set to pass data to subsequent actions (`$this->chargeId = $id`).
- Expected properties are documented with `@property-read` docblocks — Brain validates that at least one expected key exists at construction.
- Support validation via `rules()`, conditional execution via `runIf()`, delayed execution via `runIn()`, and workflow cancellation via `$this->cancelWorkflow()`.
- Can be queued by implementing `ShouldQueue`.
- Run synchronously via `MyAction::run($payload)` (wraps `dispatchSync`).
- Support `#[Sensitive('key1', 'key2')]` to automatically redact payload properties in logs, JSON, and debug output. When applied to a Workflow, all child actions inherit the sensitive keys.

### Queries

- Read-only classes for fetching data.
- Constructor parameters define inputs, `handle()` returns the result.
- Invoked via `MyQuery::run(named: $args)`.

## Development

### Requirements

- PHP 8.3+
- Laravel 11.37+ or 12.0+

### Commands

```bash
composer test          # Run full test suite (debug, refactor, lint, types, typos, unit)
composer test:unit     # Run Pest tests with coverage (min 99%)
composer test:types    # Run PHPStan static analysis
composer test:lint     # Run Pint code style check
composer test:refactor # Run Rector dry-run
composer test:typos    # Run Peck typo check
composer lint          # Fix code style with Pint
composer refactor      # Apply Rector refactorings
```

### Testing

- Tests use **Pest** with **Orchestra Testbench**.
- Feature tests live in `tests/Feature/`.
- Test fixtures (fake Actions, Workflows, Queries) live in `tests/Feature/Fixtures/`.
- Minimum coverage requirement: **99%**.

### Code Quality

- **Pint** for code style (Laravel preset).
- **PHPStan** for static analysis.
- **Rector** for automated refactoring.
- **Peck** for typo detection.
- Pre-commit hooks managed by **CaptainHook**.

## Configuration

Brain is configured in `config/brain.php`:

- `root` — Base directory for Brain classes (default: `'Brain'` → `App\Brain\`). Set to `null` for flat structure.
- `use_domains` — When `true`, organizes into domain subdirectories: `App\Brain\{Domain}\Workflows\`.
- `use_suffix` — When `true`, appends type suffix to class names (e.g., `CreateOrderWorkflow`).
- `suffixes` — Customize suffix per type: `workflow`, `action`, `query`.
- `log` — When `true`, logs all workflow and action events.
