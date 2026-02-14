# Changelog

All notable changes to `brain` will be documented in this file.

## 2.2.0 - 2026-02-13

### Added

- `#[Sensitive]` attribute for automatic payload redaction in logs, JSON, and debug output. When applied to a Process, all child tasks inherit the sensitive keys.
- `#[OnQueue]` attribute for setting a specific queue on Tasks and Processes.
- `brain:run` command for interactive Process/Task execution with `--rerun` option to replay previous executions.
- `brain:eject` command with recursive sub-process expansion, resolving target path from `composer.json` PSR-4 autoload map.
- `brain:show` redesigned with tree-style output and filtering options.
- Core guidelines for Brain architecture (Laravel Boost integration).
- PHP 8.5 support.
- PHP 8.4 testing compatibility.

### Fixed

- Use `Context::add()` to prevent tasks linking to wrong process.
- `FinalizeTaskMiddleware` now ensures tasks are finalized before proceeding to the next middleware.
- `brain:show` handles unresolved task type after eject.
- Prevent stale sensitive keys from leaking between processes.
- Use strict comparison in `in_array` calls for sensitive keys.
- Redact sensitive values in run history and re-prompt on rerun.

### Changed

- Cache reflection lookup in `getSensitiveKeys()` for better performance.
- Added missing docstrings across all source classes.

## 2.1.0 - 2025-11-10

### Added

- Enhanced process and task event logging with class name, process ID, and return output.
- Process name tracking in task failure events.

### Changed

- Removed unused `getName` method from `Process` and simplified `fireEvent`.
- Removed `fail` method and associated error event handling in favor of improved error handling.

### Fixed

- Adjusted spacing in console output for better alignment.

## 2.0.0 - 2025-11-10

### Added

- Configuration option for `root` directory to customize base namespace.
- Configuration option for `use_domains` to organize classes into domain subdirectories.
- `runIf` method returns `true` by default when called directly.

### Changed

- Simplified service provider registration.
- Updated command namespace logic to support domain-specific organization.
- Streamlined argument handling in make commands.

## 1.8.0 - 2025-10-29

### Added

- Logging configuration and event listeners for processes and tasks.
- Event dispatching for skipped and cancelled tasks.
- Tasks dispatched to queue when `ShouldQueue` is implemented.

## 1.7.1 - 2025-10-11

### Changed

- Updated docblock comments for validation rules and return types.

## 1.7.0 - 2025-10-11

### Added

- Validation rules for task properties via `rules()` method.

### Fixed

- Removed redundant error logging.

## 1.6.0 - 2025-09-23

### Added

- `toArray` method to filter task payload properties.

### Changed

- Updated method signature to accept a boolean parameter.

### Fixed

- Updated `mockery/mockery` version to `^1.6.12`.

## 1.5.0 - 2025-07-18

### Added

- `make:test` command with custom stub support for Pest tests.

## 1.4.1 - 2025-07-08

### Fixed

- Compatibility with Laravel versions below 11.33.3 that lack `newPendingDispatch()`.

## 1.4.0 - 2025-07-08

### Added

- `runIf` method for conditional task execution.

## 1.3.2 - 2025-07-01

### Fixed

- Set default value for payload in Process constructor.

## 1.3.1 - 2025-06-02

- Maintenance release.

## 1.3.0 - 2025-06-02

### Added

- Configurable suffixes for tasks, queries, and processes via `use_suffix` and `suffixes` config options.
- `make:process`, `make:query`, and `make:task` commands now respect suffix configuration.

## 1.2.1 - 2025-04-07

### Changed

- Simplified `addProperties` method by removing unused parameter.

## 1.2.0 - 2025-04-07

### Added

- Task and process type identification in `BrainMap`.
- Task properties displayed in `brain:show` verbose output.

## 1.1.5 - 2025-04-07

### Added

- Verbose output displays task details in `brain:show`.

### Changed

- Extracted initialization logic from constructor to a separate `run` method in Printer class.

## 1.1.4 - 2025-03-24

### Added

- Terminal width handling and query line formatting in Printer class.
- Exception handling for missing brain directory and empty brain map.
- Process and task line formatting with alignment functionality.

## 1.1.3 - 2025-03-13

### Changed

- Updated `ShowBrainCommand` to use typed properties and improved type hinting.

## 1.1.2 - 2025-03-13

- Maintenance release.

## 1.1.1 - 2025-03-13

### Fixed

- Updated `ShowBrainCommand` to handle `SplFileInfo` instances.

## 1.1.0 - 2025-03-10

### Added

- `brain:show` command registered in `BrainServiceProvider`.

## 1.0.3 - 2025-03-06

- Maintenance release.

## 1.0.2 - 2025-03-06

### Changed

- Updated command names and added aliases.

## 1.0.1 - 2025-03-06

### Changed

- Simplified Brain service provider registration.

## 1.0.0 - 2025-03-06

- Initial release.
