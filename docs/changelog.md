# Changelog

All notable changes to Brain are documented here.

## 2.2.0 - 2026-02-13

### Added

- `#[Sensitive]` attribute for automatic payload redaction in logs, JSON, and debug output. Process-level inheritance supported.
- `#[OnQueue]` attribute for setting a specific queue on Tasks and Processes.
- `brain:run` command for interactive Process/Task execution with `--rerun` option.
- `brain:eject` command with recursive sub-process expansion.
- `brain:show` redesigned with tree-style output and filtering.
- PHP 8.5 support.

### Fixed

- Use `Context::add()` to prevent tasks linking to wrong process.
- `FinalizeTaskMiddleware` ensures tasks are finalized before next middleware.
- Prevent stale sensitive keys from leaking between processes.
- Redact sensitive values in run history and re-prompt on rerun.

## 2.1.0 - 2025-11-10

### Added

- Enhanced process and task event logging with class name, process ID, and return output.

### Changed

- Removed unused `getName` method from `Process` and simplified `fireEvent`.

## 2.0.0 - 2025-11-10

### Added

- Configuration option for `root` directory to customize base namespace.
- Configuration option for `use_domains` for domain subdirectories.

### Changed

- Simplified service provider registration.
- Updated command namespace logic for domain organization.

## 1.8.0 - 2025-10-29

### Added

- Logging configuration and event listeners.
- Event dispatching for skipped and cancelled tasks.
- Queue dispatch when `ShouldQueue` is implemented.

## 1.7.0 - 2025-10-11

### Added

- Validation rules for task properties via `rules()` method.

## 1.6.0 - 2025-09-23

### Added

- `toArray` method for task payloads.

## 1.5.0 - 2025-07-18

### Added

- `make:test` command with custom stub support.

## 1.4.0 - 2025-07-08

### Added

- `runIf` method for conditional task execution.

## 1.3.0 - 2025-06-02

### Added

- Configurable suffixes for tasks, queries, and processes.

## 1.2.0 - 2025-04-07

### Added

- Task and process type identification in `BrainMap`.
- Task properties in `brain:show` verbose output.

## 1.1.0 - 2025-03-10

### Added

- `brain:show` command.

## 1.0.0 - 2025-03-06

- Initial release.
