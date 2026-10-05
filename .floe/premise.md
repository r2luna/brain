## Goal
Add CRAP and cyclomatic complexity gates to the pre-commit hook and refactor `Printer::collectDomainItems` (complexity 20).

## Scope
- `scripts/quality/complexity.php` (staged src files, max 10) and `scripts/quality/crap.php` (threshold 30, reads `.phpunit.cache/crap4j.xml`).
- `composer.json`: `test:unit` writes crap4j, `test:crap` appended to `composer test`.
- `captainhook.json`: complexity gate before `composer test`.
- Fix what blocks `composer test` locally so the hook passes.

## Out of scope
Refactoring the other methods above complexity 10.

## Done when
A commit goes through with the hook active.
