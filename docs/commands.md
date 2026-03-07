# Commands

Brain provides several Artisan commands for scaffolding and inspecting your architecture.

## Scaffolding

### `make:process`

Create a new Process class:

```bash
php artisan make:process CreateUser
```

With domains enabled, you'll be prompted for a domain name.

### `make:task`

Create a new Task class:

```bash
php artisan make:task RegisterUser
```

### `make:query`

Create a new Query class:

```bash
php artisan make:query GetUserByEmail
```

You can optionally specify a model when prompted.

## Visualization

### `brain:show`

Visualize your entire Brain structure in the terminal:

```bash
php artisan brain:show
```

```
PROC    CreateUserProcess ·······································
PROC    PaymentSucceededProcess ························· chained
TASK    NotifyStaffTask ·································· queued
TASK    RegisterUserTask ········································
TASK    SendWelcomeEmailTask ····································
QERY    GetPaymentQuery ·········································
QERY    GetUserByEmailQuery ·····································
```

### Filtering by Type

```bash
php artisan brain:show -p              # only processes
php artisan brain:show -t              # only tasks
php artisan brain:show -Q              # only queries
php artisan brain:show -p -t           # processes and tasks
```

### Filtering by Name

```bash
php artisan brain:show --filter=User
php artisan brain:show -p --filter=Payment
```

When combining `-p` with `--filter`, matching sub-task names show the parent process with only the matching sub-tasks. Matching process names show all sub-tasks.

### Verbosity

```bash
php artisan brain:show -v              # show sub-tasks inside processes
php artisan brain:show -vv             # also show task properties
```

```
PROC    PaymentSucceededProcess ························· chained
        ├── 1. T SavePaymentTask ························· queued
        └── 2. T InviteUserTask ·································
TASK    CreateCommentTask ·······································
           → user_id: int
           ← comment: \Comment|null
QERY    ExampleQuery ············································
```

- `→` — input property (`@property-read`)
- `←` — output property (`@property`)

## Execution

### `brain:run`

Interactively execute a Process or Task from the terminal:

```bash
php artisan brain:run
```

The command guides you through:

1. **Select a target** — Search and pick any Process or Task
2. **Choose dispatch mode** — Sync or Async
3. **Fill payload** — Enter values for required properties
4. **Preview** — Review before executing
5. **Execute** — Run and see the result

### Rerunning Previous Executions

Every successful run is saved to history. Use `--rerun` to replay:

```bash
php artisan brain:run --rerun
```

Search through past runs, preview the saved payload, and re-execute.
