# Expense Tracker System

Expense Tracker System is a PHP and MySQL web application for recording personal expenses, organizing them by category, managing budgets, scheduling recurring expenses, uploading receipts, and generating reports.

## Main Features

- User registration, login, logout, and password recovery
- Expense creation, editing, deletion, filtering, and CSV export
- Category and monthly budget management
- Recurring expense scheduling
- Dashboard summaries and chart-based reporting
- Date-wise, month-wise, and year-wise reports

## Project Structure

```text
Expense-Tracker-System/
├── public/        Web root: the application pages, assets/ (css, js, fonts, images, plugins) and uploads/
├── src/           Business logic: expense and report helper functions
├── config/        Database connection settings
├── templates/     Shared layout pieces (header, sidebar, footer)
├── database/      Schema (database.sql) and demo seed data
├── tests/         Unit tests and the browser end-to-end test
├── docs/          Acceptance checklist and presentation material
├── .github/       CI workflows (syntax check, tests + coverage, build)
├── build.php      Builds the release package (composer build)
├── index.php      Redirects the old address to public/
└── composer.json  Dependencies and scripts (lint, test, test:coverage, build)
```

Uploaded receipts are stored in `public/uploads/receipts/`.

## Report

The software engineering report is maintained in a separate repository and published at
<https://unibo-dtm-se-2324-ets.github.io/report/>. It is deliberately not kept in this
repository, so that there is only one copy of the document.

## Local Deployment

1. Install XAMPP.
2. Copy the project folder into `htdocs`.
3. Start Apache and MySQL.
4. Create the MySQL database.
5. Import `database/database.sql`.
6. Update `config/database.php` if the local database credentials are different.
7. Open `http://localhost/Expense-Tracker-System/public/` in a browser.

## Build And Checks

- PHP syntax check workflow: `.github/workflows/main.yml`
- CI workflow (`.github/workflows/php.yml`): validates Composer files, lints, runs the unit tests with a pcov code coverage report (uploaded as the `coverage-report` artifact), runs the Playwright browser test against the app with a MySQL service, then builds the release package (`expense-tracker.zip`) once both pass

Locally, selected syntax checks can be run with:

```bash
composer run lint
```

The unit tests cover the helper functions and, against a separate `detsdb_test` MySQL database (created automatically, your real data is never touched), the database functions. If no MySQL server is reachable the database tests are skipped. Set `TEST_DB_HOST`, `TEST_DB_USER`, `TEST_DB_PASS` and `TEST_DB_NAME` to change the connection.

Automated unit tests can be run with:

```bash
composer test
```

Unit tests with a code coverage report (needs the PCOV or Xdebug extension; the HTML report is written to `build/coverage/html`):

```bash
composer test:coverage
```

The release package can be built locally with `composer build`, which writes `build/expense-tracker.zip`.

### PHP test coverage

Measured with PHPUnit and PCOV (78 tests, all passing), 2 October 2026:

| File | Statements | Methods | Covered statements |
|---|---|---|---|
| `src/expense-helpers.php` | 87.1% | 100.0% | 182/209 |
| `src/report-helpers.php` | 80.8% | 100.0% | 21/26 |
| **Total** | **86.4%** | **100.0%** | **203/235** |

The table is regenerated on every push by the CI workflow and appears on the run's Summary page on GitHub; the full HTML report is attached to the run as the `coverage-report` artifact. The page files are not part of the unit-test coverage and are checked by the browser test below.

Playwright full-site browser tests can be run with:

```bash
python -m pip install -r requirements-dev.txt
python -m playwright install chromium
python tests/e2e/playwright_smoke.py
```

The Playwright test uses Chromium against the local XAMPP application. Start Apache and MySQL, make sure the `detsdb` database is imported, then run:

```bash
$env:APP_BASE_URL="http://localhost/Expense-Tracker-System/public/"
python tests/e2e/playwright_smoke.py
```

To also check the published report site:

```bash
$env:RUN_REPORT_CHECK="1"
python tests/e2e/playwright_smoke.py
```

## License

This project is released under the MIT License.
