# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.

## [Unreleased]

### Added
- Services in `src/` that hold all the logic and every database query of the application:
  `expense-service.php`, `account-service.php`, `item-service.php`, `category-service.php`,
  `recurring-service.php`, `dashboard-service.php` and `report-service.php`. The expense service was
  built test-first: the failing tests were committed before the implementation.
- 263 tests (from 80): unit tests, database tests against a separate test database, and failure tests
  that make a table unavailable to check that every service reports a failure instead of crashing.
- The browser test now also checks the CSV exports, editing and deleting an expense, the dashboard
  and every report page.
- Test coverage of `src/` is now 100% (999 of 999 statements).

### Security
- Login, registration, password recovery, password change, the item list and the report pages no
  longer build SQL from user input; they use prepared statements, which closes SQL injection.
- Passwords are stored with `password_hash()` instead of MD5. Accounts that still have an MD5 hash
  keep working and are upgraded the next time the user logs in.
- The session id is renewed at login, and the item names and the dates shown by the older report
  are escaped before they are printed.

### Fixed
- Deleting something that does not exist, or that belongs to another user (an expense, an item, a
  category, a budget or a recurring expense), is reported as a failure instead of a success.
- The dashboard's previous month is correct on the last days of a month (on 31 March it used to
  look at March itself).

### Changed
- All pages use the services; the pages are 765 lines shorter (4,519 down to 3,754) and contain no database query.
- CI fails when the statement coverage drops below 95% (it was 80%).
- `expense_handle_receipt_upload()` accepts an optional upload folder and file mover so that it can be
  tested.
- Tests are split into `tests/unit`, `tests/integration` and `tests/e2e`.
- `build.php` moved to `scripts/build.php`; the Sass source moved to `resources/sass/`.
- Presentations moved to `docs/presentations/`.
- Added `.editorconfig` and `.gitattributes`.

### Removed
- Unused third-party code: `public/assets/plugins/` and unreferenced CSS and JS files.

## [1.1.0] - 2026-10-16

### Added
- `CHANGELOG.md`, `CONTRIBUTING.md` and a `VERSION` file.
- CI now fails when the statement coverage drops below 80%.

## [1.0.0] - 2026-10-14

First tagged release.

### Added
- Expense tracking: registration, login, password recovery, expense creation, editing, deletion,
  filtering and CSV export.
- Categories, monthly budgets and recurring expenses.
- Dashboard summaries and date-wise, month-wise and year-wise reports.
- Automated tests: 78 PHPUnit tests (helper functions, database functions against a separate
  `detsdb_test` database, syntax checks) and a Playwright browser test.
- Code coverage reporting with PCOV (86.4% of the code in `src/`), published as a table on every
  CI run and as a downloadable HTML report.
- Continuous integration with GitHub Actions: lint, tests with coverage, browser test and release
  package build.
- Build automation: `build.php` / `composer build` creates `expense-tracker.zip`.
- Release workflow that publishes a GitHub Release when a version tag is pushed.

### Changed
- Project restructured to a standard layout: `public/` web root, `src/` business logic, `config/`,
  `templates/`, `database/`, `docs/` and `tests/`.
- `config/database.php` accepts optional `DB_HOST`, `DB_USER`, `DB_PASS` and `DB_NAME` environment
  variables; the defaults are unchanged.

### Removed
- Committed Sass cache and unused sample table data.

[Unreleased]: https://github.com/unibo-dtm-se-2324-ETS/artifact/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/unibo-dtm-se-2324-ETS/artifact/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/unibo-dtm-se-2324-ETS/artifact/releases/tag/v1.0.0
