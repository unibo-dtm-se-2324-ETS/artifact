---
title: Validation
nav_order: 6
---

# Validation

## Testing Approach

The project was checked in three ways:

1. Code-level checks were used to verify that the PHP files do not contain syntax errors.
2. PHPUnit automated tests were used to verify reusable PHP helper functions and syntax-check all PHP pages.
3. Manual browser testing was used to verify that the website workflows behave correctly from the user's point of view.

The repository now includes automated unit tests for helper logic. Full end-to-end application validation is still mainly manual because the project is a PHP/MySQL web application that depends on a local XAMPP database and browser interaction.

## Code Checks

The PHP source code was checked with the PHP command-line syntax checker from the local XAMPP installation.

Environment:

- Operating system: Windows
- Local server stack: XAMPP
- PHP version used for checking: PHP 8.2.12
- Database: MySQL/MariaDB through XAMPP

Commands used:

```powershell
C:\xampp\php\php.exe -v
Get-ChildItem -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
Get-ChildItem includes -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

Result:

- No syntax errors were detected in the root PHP application pages.
- No syntax errors were detected in the PHP files inside `includes/`.
- The same type of syntax check is automated in GitHub Actions through the `PHP Syntax Check` workflow.

## Automated Unit Tests

PHPUnit was added as a development dependency and used to test reusable helper functions from:

- `includes/expense-helpers.php`
- `includes/report-helpers.php`

It also includes a syntax smoke test that runs PHP's `-l` checker against all root PHP pages and all PHP files in `includes/`.

The test files are stored in:

- `tests/unit/ExpenseHelpersTest.php`
- `tests/unit/ReportHelpersTest.php`
- `tests/unit/SyntaxCheckTest.php`

Command used:

```powershell
composer test
```

Result:

```text
PHPUnit 11.5.55
44 tests, 57 assertions
OK
```

The automated tests check currency options, currency formatting, money formatting, HTML escaping, month-key generation, selected-currency validation, budget progress calculation, CSRF token verification, receipt upload validation, receipt deletion, report helper behavior, and PHP syntax validity for application pages.

## Manual Website Testing Procedure

The website was tested locally through XAMPP using the following procedure:

1. Start Apache and MySQL in XAMPP.
2. Place the project inside `htdocs`.
3. Import the database script into MySQL.
4. Open `http://localhost/Expense-Tracker-System/` in a browser.
5. Test each main workflow as a normal user.
6. Confirm the visible result in the browser and, where relevant, confirm that the data is stored or changed in the database.

## Sample Test Cases

| Test Case | Test Data / Action | Expected Result | Status |
| --- | --- | --- | --- |
| User registration | Enter valid full name, email, mobile number, and password. | New user account is created and can be used for login. | Passed manually |
| Login | Enter correct email and password. | User is redirected to dashboard. | Passed manually |
| Logout | Click logout from an authenticated session. | Session ends and protected pages require login again. | Passed manually |
| Add expense | Enter valid date, item, category, amount, currency, and optional notes. | Expense is saved successfully and appears in the expense list. | Passed manually |
| Edit expense | Change an existing expense amount or category. | Existing expense data is updated correctly. | Passed manually |
| Delete expense | Delete an existing expense record. | Expense is removed from the list. | Passed manually |
| Search and filter | Filter by text, date range, category, amount range, or currency. | Only matching expense records are displayed. | Passed manually |
| Add category | Enter a unique category name. | Category is stored and appears in category selectors. | Passed manually |
| Save budget | Select category, month, currency, and amount. | Budget appears in category and dashboard views. | Passed manually |
| Add recurring expense | Create weekly or monthly recurring schedule. | Recurring rule is stored and due expenses can be generated. | Passed manually |
| Generate report | Select date-wise, month-wise, or year-wise report period. | Report summary, details, and charts are displayed. | Passed manually |
| Export CSV | Click export after filtering expenses. | CSV file downloads and contains the selected expense data. | Passed manually |

## Requirement Acceptance Matrix

| Requirement | Acceptance procedure | Final result |
| --- | --- | --- |
| FR1 Register user | Submit the registration form with valid personal data and password. | User account is created. |
| FR2 Login and logout | Log in with valid credentials, access dashboard, then log out. | Protected pages are available only during the session. |
| FR3 Password workflows | Use forgot-password, reset-password, and change-password pages with valid data. | Password-related workflows complete without breaking login. |
| FR4 Profile and defaults | Update profile data, default currency, and default category. | Updated values are stored and reused where applicable. |
| FR5 Expense items | Add a reusable expense item. | Item appears as an available option for expenses. |
| FR6 Add expense | Submit a complete valid expense form. | Expense is stored and displayed in the list/dashboard. |
| FR7 Edit and delete expense | Modify an existing expense, then delete it. | Changes are saved, and deleted records no longer appear. |
| FR8 Search and filter | Apply text, date, amount, category, and currency filters. | Results contain only matching expenses. |
| FR9 CSV export | Export filtered expenses. | Downloaded CSV matches the filtered result set. |
| FR10 Categories and budgets | Create categories and assign monthly budgets. | Categories and budget status are visible in relevant pages. |
| FR11 Recurring rules | Create weekly and monthly recurring expenses. | Recurring rules are stored with the correct frequency. |
| FR12 Automatic recurring generation | Open the application after a recurring item becomes due. | Due recurring expenses are inserted into the expense table. |
| FR13 Dashboard summaries | Open dashboard with existing expense data. | Today, weekly, monthly, yearly, and total summaries are displayed. |
| FR14 Charts | Open dashboard/report pages with categorized expenses. | Chart-based insights render with the expected data. |
| FR15 Reports | Generate daily, monthly, and yearly reports. | Report totals, details, and charts match the selected period. |

## Validation Results

The implemented pages support the main workflow successfully:

- User authentication features are present.
- Expense CRUD operations are implemented.
- Filtering and CSV export are implemented.
- Categories and budgets are integrated with the dashboard.
- Recurring expense automation is implemented.
- Reporting is available in date-wise, month-wise, and year-wise formats.

## Quality Observations

- Newer pages use prepared statements and reusable helpers more consistently than older pages.
- CSRF protection is present in several newer forms.
- Password storage still uses MD5 in older authentication logic, which is not secure by modern standards.
- Schema updates are triggered at runtime through helper logic instead of a dedicated migration system.
- Automated unit and syntax-smoke tests are included; browser-level automation is not yet included.

## Suggested Validation Improvements

- Add browser-level automation with Playwright or Selenium.
- Add integration tests for login, expense creation, recurring processing, and report generation.
- Add security-focused validation for authentication and file uploads.
