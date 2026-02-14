# Brain

Brain (`r2luna/brain`) is a Laravel package that organizes business logic into three core concepts: **Processes**, **Tasks**, and **Queries**.

## Project Structure

```
src/
├── Process.php          # Base Process class
├── Task.php             # Base Task class
├── Query.php            # Base Query class
├── BrainServiceProvider.php
├── Attributes/          # PHP attributes (e.g., Sensitive)
├── Console/             # Artisan commands (make:process, make:task, make:query, brain:show, brain:run)
├── Exceptions/          # InvalidPayload, etc.
├── Facades/
├── Processes/           # Process infrastructure (Events, Listeners, Middleware)
├── Tasks/               # Task infrastructure (Events, Listeners, Middleware)
├── Queries/             # Query infrastructure (Events, Listeners)
└── Tests/               # Test stubs
tests/
├── Feature/             # Feature tests (Pest)
│   ├── Fixtures/        # Test fixture classes
│   ├── Console/         # Command tests
│   └── *Test.php        # Process, Task, Query, etc.
├── TestCase.php         # Base test case (Orchestra Testbench)
└── Pest.php
```

## Core Concepts

| Concept | Purpose | Base Class | Invocation |
|---------|---------|------------|------------|
| Process | Orchestrates a sequence of tasks in a DB transaction | `Brain\Process` | `MyProcess::dispatchSync($payload)` |
| Task    | A single unit of work that mutates state | `Brain\Task` | `MyTask::dispatchSync($payload)` |
| Query   | A read-only operation that returns data | `Brain\Query` | `MyQuery::run($args)` |

### Processes

- Run a list of tasks in order, wrapped in a database transaction.
- Tasks are defined in the `$tasks` array.
- Support dynamic task addition via `addTask()`.
- Support chaining (queued tasks as a Bus chain) via `$chain = true`.
- Sub-processes can be nested by adding a Process class to `$tasks`.

### Tasks

- Receive a payload object and implement `handle()`, returning `$this`.
- Payload properties are accessed via magic methods (`$this->userId`).
- New properties can be set to pass data to subsequent tasks (`$this->chargeId = $id`).
- Expected properties are documented with `@property-read` docblocks — Brain validates that at least one expected key exists at construction.
- Support validation via `rules()`, conditional execution via `runIf()`, delayed execution via `runIn()`, and process cancellation via `$this->cancelProcess()`.
- Can be queued by implementing `ShouldQueue`.
- Support `#[Sensitive('key1', 'key2')]` to automatically redact payload properties in logs, JSON, and debug output. When applied to a Process, all child tasks inherit the sensitive keys.

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
- Test fixtures (fake Tasks, Processes, Queries) live in `tests/Feature/Fixtures/`.
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
- `use_domains` — When `true`, organizes into domain subdirectories: `App\Brain\{Domain}\Processes\`.
- `use_suffix` — When `true`, appends type suffix to class names (e.g., `CreateOrderProcess`).
- `suffixes` — Customize suffix per type: `task`, `process`, `query`.
- `log` — When `true`, logs all process and task events.
