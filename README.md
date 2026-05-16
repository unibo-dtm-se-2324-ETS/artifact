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

- Root `*.php` files contain the main application pages.
- `includes/` contains database connection, layout includes, and shared helper functions.
- `css/`, `js/`, `assets/`, and `fonts/` contain frontend resources.
- `uploads/receipts/` stores uploaded receipt files.
- `report/` contains the software engineering report site.
- `.github/workflows/` contains CI/CD workflow definitions.

## Local Deployment

1. Install XAMPP.
2. Copy the project folder into `htdocs`.
3. Start Apache and MySQL.
4. Create the MySQL database.
5. Import `database.sql`.
6. Update `includes/dbconnection.php` if the local database credentials are different.
7. Open `http://localhost/Expense-Tracker-System/` in a browser.

## Build And Checks

- PHP syntax check workflow: `.github/workflows/main.yml`
- Release artifact workflow: `.github/workflows/artifact.yml`
- Report site deployment workflow: `.github/workflows/report-site.yml`

Locally, selected syntax checks can be run with:

```bash
composer run lint
```

## License

This project is released under the MIT License.
