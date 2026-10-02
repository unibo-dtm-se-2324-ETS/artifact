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

- Root `*.php` files are the application pages (kept at the web root so the URLs stay stable).
- `src/` contains the shared business logic (expense and report helper functions).
- `config/` contains the database connection settings.
- `templates/` contains the shared layout pieces (header, sidebar, footer).
- `assets/` contains the frontend resources (`css/`, `js/`, `fonts/`, `images/`, `plugins/`) and the Sass source.
- `database/` contains the schema (`database.sql`) and the demo seed data.
- `uploads/receipts/` stores uploaded receipt files.
- `tests/` contains the unit tests and the browser end-to-end test.
- `docs/` contains the acceptance checklist and the presentation material.
- `.github/workflows/` contains CI/CD workflow definitions.

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
7. Open `http://localhost/Expense-Tracker-System/` in a browser.

## Build And Checks

- PHP syntax check workflow: `.github/workflows/main.yml`
- CI workflow (`.github/workflows/php.yml`): validates Composer files, lints, runs the unit tests with a pcov code coverage report (uploaded as the `coverage-report` artifact), then builds the release package (`expense-tracker.zip`) once the tests pass

Locally, selected syntax checks can be run with:

```bash
composer run lint
```

Automated unit tests can be run with:

```bash
composer test
```

Unit tests with a code coverage report (needs the PCOV or Xdebug extension; the HTML report is written to `build/coverage/html`):

```bash
composer test:coverage
```

The release package can be built locally with `composer build`, which writes `build/expense-tracker.zip`.

Playwright full-site browser tests can be run with:

```bash
python -m pip install -r requirements-dev.txt
python -m playwright install chromium
python tests/e2e/playwright_smoke.py
```

The Playwright test uses Chromium against the local XAMPP application. Start Apache and MySQL, make sure the `detsdb` database is imported, then run:

```bash
$env:APP_BASE_URL="http://localhost/Expense-Tracker-System/"
python tests/e2e/playwright_smoke.py
```

To also check the published report site:

```bash
$env:RUN_REPORT_CHECK="1"
python tests/e2e/playwright_smoke.py
```

## License

This project is released under the MIT License.
