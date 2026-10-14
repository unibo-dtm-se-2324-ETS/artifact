# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.

## [Unreleased]

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

[Unreleased]: https://github.com/unibo-dtm-se-2324-ETS/artifact/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/unibo-dtm-se-2324-ETS/artifact/releases/tag/v1.0.0
