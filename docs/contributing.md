# Contributing

Contributions are welcome and accepted via pull requests.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure code style passes: `composer lint`
- Send a coherent commit history — each commit should be meaningful
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts
- We follow [SemVer](http://semver.org/)

## Setup

Clone your fork, then install dependencies:

```bash
composer install
```

## Commands

```bash
composer lint          # Fix code style
composer test          # Run full test suite
composer test:types    # Static analysis
composer test:unit     # Unit tests with coverage
```
