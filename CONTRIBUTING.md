# Contributing

Thank you for helping with the Expense Tracker System. This page explains how we work.

## Branch and pull request workflow

1. Do not commit directly to `main`. It always contains working, tested code.
2. Create a short-lived branch from `main`, named after the change:
   `feat/recurring-weekly`, `fix/budget-rounding`, `docs/readme-coverage`.
3. Commit your work in small steps (see the commit format below).
4. Push the branch and open a pull request into `main`.
5. The **CI** workflow must be green (lint, tests, coverage, browser test, build) and at least one
   other person should review the change before it is merged.
6. Merge the pull request, then delete the branch.

## Commit format (Conventional Commits)

```text
<type>(<optional scope>): <short summary in the imperative>

<optional body explaining why>
```

| Type | Use it for | Version effect |
|---|---|---|
| `feat` | a new feature | MINOR |
| `fix` | a bug fix | PATCH |
| `docs` | documentation only | none |
| `test` | adding or changing tests | none |
| `refactor` | code change that is neither a fix nor a feature | none |
| `ci` / `build` | workflows, build scripts, dependencies | none |
| `chore` | anything else (cleanup) | none |

A breaking change is marked with `!` (`feat!: ...`) or a `BREAKING CHANGE:` line and bumps the
MAJOR version. Examples:

```text
feat(recurring): support weekly recurring expenses
fix(budget): clamp progress between 0 and 100
docs: add the coverage table to the README
```

## Running the checks

Install the dependencies once: `composer install`.

| Command | What it does |
|---|---|
| `composer lint` | checks every PHP file for syntax errors |
| `composer test` | runs the PHPUnit tests |
| `composer test:coverage` | runs the tests and writes a coverage report to `build/coverage/` (needs PCOV or Xdebug) |
| `composer build` | creates `build/expense-tracker.zip` |
| `python tests/e2e/playwright_smoke.py` | browser test; needs Apache and MySQL running and the setup in the README |

The database tests use their own `detsdb_test` database and are skipped when MySQL is not
reachable. They never touch your real data.

Add or update tests for every change, and run `composer test` before opening a pull request.

## Releasing a version

Versions follow [Semantic Versioning](https://semver.org/).

1. Move the entries from `[Unreleased]` to a new version section in `CHANGELOG.md`.
2. Update the number in the `VERSION` file.
3. Commit with `chore: release vX.Y.Z`, merge to `main`.
4. Tag and push: `git tag -a vX.Y.Z -m "Expense Tracker vX.Y.Z"` then `git push <remote> vX.Y.Z`.

The **Release** workflow then builds the package and publishes the GitHub Release automatically.
