# Expense Tracker System

## Home

### Project Title
Expense Tracker System

### Author
Zagros Baban

### Abstract
The Expense Tracker System is a web-based personal finance management application developed using PHP, MySQL, HTML, CSS, JavaScript, Bootstrap, and Chart.js. The system allows registered users to record daily expenses, organize them by category, attach receipts, define monthly budgets, and generate analytical reports. In addition to basic expense entry, the project supports multi-currency tracking, recurring expenses, category-level budget monitoring, and dashboard-based visual summaries. The main purpose of the system is to help users monitor their spending habits and make better financial decisions through structured data collection and reporting. The project follows a practical modular structure in which the user interface, database access, helper functions, and reporting logic are separated into different files. This improves maintainability and makes the system easier to extend. Overall, the system demonstrates the application of software engineering principles to the design and implementation of a useful real-world financial management solution.

## Concept

### Introduction
Managing personal expenses manually is difficult, especially when a user wants to review spending patterns over time, compare spending across categories, or keep records such as receipts and recurring payments. Many people rely on notebooks or simple spreadsheets, but those methods are error-prone and do not provide automatic summaries or visual insights.

This project was developed to solve that problem by providing a centralized digital platform where users can record, edit, categorize, and analyze their expenses. The system transforms raw expense entries into meaningful information through dashboards, budgets, and date-based reports.

### Problem Statement
Individuals often face problems in tracking their day-to-day expenses, understanding where their money is spent, and controlling monthly budgets. Traditional manual methods do not provide enough automation, data consistency, or analytical support. Therefore, there is a need for an affordable and easy-to-use web application that can store expense data securely and produce useful reports for decision-making.

### Aim of the Project
The aim of this project is to develop a web-based expense management system that helps users record and monitor their personal spending efficiently.

### Objectives
- To provide secure user registration and login functionality.
- To allow users to add, edit, delete, and view expense records.
- To organize expenses using items, categories, notes, and receipt uploads.
- To support multiple currencies for expense tracking.
- To let users define monthly budgets by category.
- To automate repeated spending through recurring expense scheduling.
- To generate day-wise, month-wise, and year-wise reports.
- To present spending insights visually through dashboard charts and summaries.

### Scope
The scope of this project is limited to single-user personal expense tracking after authentication. It covers expense management, report generation, profile management, and budget monitoring. It does not include online payments, banking integration, role-based administration, tax calculation, or mobile-native deployment.

## Requirements

### Functional Requirements
1. The system shall allow users to register with name, email, mobile number, and password.
2. The system shall allow registered users to log in and log out.
3. The system shall support password reset and password change features.
4. The system shall allow users to manage a personal profile.
5. The system shall allow users to create and manage expense items.
6. The system shall allow users to add expenses with date, item, amount, category, notes, currency, and optional receipt.
7. The system shall allow users to edit and delete expense records.
8. The system shall allow users to search and filter expenses by text, category, date range, amount range, and currency.
9. The system shall allow users to export filtered expense data as CSV.
10. The system shall allow users to manage categories and assign monthly budgets.
11. The system shall allow users to create recurring expenses with weekly or monthly frequency.
12. The system shall automatically post due recurring expenses into the main expense table.
13. The system shall provide dashboard summaries such as today, weekly, monthly, yearly, and total spending.
14. The system shall show visual charts for spending trends and top categories.
15. The system shall generate daily, monthly, and yearly expense reports with chart-based summaries and CSV export.

### Non-Functional Requirements
- Usability: The interface should be simple enough for ordinary users without technical knowledge.
- Performance: The system should load dashboard and reports within acceptable time for normal personal datasets.
- Maintainability: Common logic should be reused through helper functions and modular PHP files.
- Reliability: The system should correctly store and retrieve expense data without data loss during normal usage.
- Portability: The project should run on a typical XAMPP environment with PHP and MySQL.
- Security: Sessions should protect authenticated pages, inputs should be validated, and sensitive operations should be restricted to logged-in users.

### Software and Hardware Requirements

#### Software
- Operating System: Windows
- Server Stack: XAMPP
- Language: PHP
- Database: MySQL
- Frontend: HTML, CSS, JavaScript, Bootstrap
- Charts: Chart.js
- Browser: Chrome, Edge, or Firefox

#### Hardware
- Standard personal computer or laptop
- Minimum 4 GB RAM
- Stable local development environment

## Design

### System Architecture
The project follows a simple modular web architecture:

1. Presentation Layer
   This includes PHP pages mixed with HTML, CSS, Bootstrap, and JavaScript. It is responsible for forms, tables, navigation, and charts.

2. Business Logic Layer
   Shared logic is placed in helper files such as `includes/expense-helpers.php` and `includes/report-helpers.php`. These files handle currency formatting, schema checks, CSRF protection, category utilities, recurring processing, and report formatting.

3. Data Access Layer
   Database communication is handled through MySQLi using the shared connection file `includes/dbconnection.php`. Several modules use prepared statements, especially in newer expense-management pages.

### Main Modules
- Authentication Module
  Covers registration, login, forgot password, reset password, and password change.
- Dashboard Module
  Displays overview metrics, charts, latest expense, category analysis, and budget health.
- Expense Management Module
  Handles add, edit, delete, filter, and export of expense records.
- Item Management Module
  Maintains a reusable list of expense item names.
- Category and Budget Module
  Manages user-defined categories and monthly spending limits.
- Recurring Expense Module
  Automatically inserts scheduled weekly or monthly expenses.
- Reporting Module
  Provides day-wise, month-wise, and year-wise summaries with charts and CSV export.
- Profile Module
  Stores basic user data and default preferences such as currency and default category.

### Database Design
From the codebase, the project uses the following main tables:

#### `tbluser`
- `ID`
- `FullName`
- `MobileNumber`
- `Email`
- `Password`
- `RegDate`
- `DefaultCurrency`
- `DefaultCategoryId`

#### `tblexpense`
- `ID`
- `UserId`
- `ExpenseDate`
- `ExpenseItem`
- `ExpenseCost`
- `Currency`
- `CategoryId`
- `Notes`
- `ReceiptPath`

#### `tblitems`
- `ID`
- `UserId`
- `ItemName`
- `CreatedAt`

#### `tblcategories`
- `ID`
- `UserId`
- `CategoryName`
- `CreatedAt`

#### `tblbudgets`
- `ID`
- `UserId`
- `CategoryId`
- `BudgetMonth`
- `Currency`
- `BudgetAmount`
- `CreatedAt`
- `UpdatedAt`

#### `tblrecurring`
- `ID`
- `UserId`
- `ExpenseItem`
- `ExpenseCost`
- `Currency`
- `CategoryId`
- `Notes`
- `Frequency`
- `StartDate`
- `NextRunDate`
- `LastRunDate`
- `IsActive`
- `CreatedAt`

### Input Design
The system uses form-based inputs for registration, login, adding expenses, filtering reports, updating profile information, and setting budgets. Input controls include text fields, password fields, date pickers, month selectors, dropdown lists, numeric inputs, file uploads, and action buttons.

### Output Design
The system produces outputs in the form of:
- Dashboard cards
- Tables of expense records
- Chart-based visual summaries
- Budget progress indicators
- CSV exports
- Daily, monthly, and yearly report pages

## Development

### Development Approach
The project was developed incrementally. The first version used straightforward PHP and SQL mixed inside view files, while later improvements introduced helper functions, prepared statements, CSRF handling, better validation, modernized layout styling, and more modular logic. This approach allowed the system to grow in stages while keeping existing features working.

### Tools and Technologies Used
- PHP for server-side programming
- MySQL for persistent data storage
- HTML and CSS for page structure and styling
- Bootstrap for responsive UI components
- JavaScript and jQuery for frontend behavior
- Chart.js for visual analytics
- XAMPP for local server execution

### Implementation Highlights

#### Authentication
Users can create accounts, log in, reset forgotten passwords, and change passwords after logging in.

#### Expense Processing
The add and edit expense workflows include validation for date, item, amount, category, and optional receipt uploads. Expenses are connected to categories and currencies, which improves reporting accuracy.

#### Budget Tracking
Users can set budgets by category and month. The dashboard compares actual expenses with defined budgets and highlights warning and over-budget states.

#### Recurring Expenses
Recurring entries are stored in a dedicated table. On page access, due entries are automatically inserted into the main expense table based on weekly or monthly schedules.

#### Reporting
The reporting module summarizes expense totals by day, month, and year. Each report includes total amount, highest period, record count, visual charts, and export to CSV.

## Validation

### Testing Approach
The project does not include automated unit or integration tests in the repository, so validation is mainly manual. Manual testing can still be structured around core workflows.

### Sample Test Cases

| Test Case | Input | Expected Result |
| --- | --- | --- |
| User registration | Valid name, email, mobile, password | User account is created successfully |
| Login | Correct email and password | User is redirected to dashboard |
| Add expense | Valid date, item, amount, category | New expense is saved and listed |
| Edit expense | Update amount or category | Existing expense is updated |
| Delete expense | Valid selected record | Expense is removed from list |
| Add category | New category name | Category is stored successfully |
| Save budget | Category, month, amount | Budget is available in category view and dashboard |
| Add recurring expense | Weekly or monthly schedule | Future expense is auto-generated on due date |
| Generate report | Valid date or month range | Report table and charts are displayed |
| Export CSV | Click export | Data downloads in CSV format |

### Validation Results
The project supports the complete main workflow successfully:
- User authentication is available.
- Expense CRUD operations are implemented.
- Filters and CSV export are implemented.
- Budget and category features are integrated with the dashboard.
- Recurring expense automation exists.
- Reporting is implemented in three time scopes.

### Quality Observations
The project shows practical functionality, but validation also reveals areas for improvement:
- Some pages use prepared statements, while older pages still use direct SQL queries.
- Passwords are stored using MD5, which is not secure by modern standards.
- Schema migration logic is triggered at runtime from helper functions instead of using dedicated migration scripts.
- The project has no automated test suite.

## Release

### Release Description
The current version provides a complete local-deployment version of the Expense Tracker System. It offers a full personal expense tracking workflow from account creation to analytical reporting.

### Release Features
- Secure session-based access to internal pages
- Dashboard with summaries and charts
- Expense add, edit, delete, filter, and export
- Category and budget management
- Recurring expense scheduling
- User profile and default settings
- Date-wise, month-wise, and year-wise reports

## Deployment

### Deployment Environment
The project is intended for deployment on a local Apache and MySQL stack such as XAMPP.

### Deployment Steps
1. Install XAMPP.
2. Place the project folder inside `htdocs`.
3. Start Apache and MySQL from the XAMPP control panel.
4. Create the MySQL database used by the project.
5. Configure database connection settings in `includes/dbconnection.php`.
6. Import the base database structure if available.
7. Open the project in the browser through `http://localhost/Expense-Tracker-System/`.

### Deployment Notes
Some additional tables and columns are created automatically by helper functions when certain pages are loaded. This helps the system adapt to newer features, although a dedicated migration process would be better in a professional environment.

## CI/CD

### Current Status
The repository includes GitHub Actions workflows for packaging and report-site publishing, along with a PHP Composer workflow file.

### Workflows Present in the Repository
- `artifact.yml`
  Builds a zipped release artifact from the repository contents.
- `report-site.yml`
  Builds the `report/` folder with Jekyll and deploys it to GitHub Pages.
- `php.yml`
  Validates and installs Composer dependencies in CI, although the current repository does not yet include `composer.json` or `composer.lock`.

### Assessment
The project has an initial CI/CD setup for packaging and documentation publishing. However, the application pipeline is not yet fully aligned with the current codebase because:

- there is no automated test suite for the PHP application,
- there is no Composer project file in the repository,
- there is no database migration or seed step in CI,
- there are no smoke tests for deployed behavior.

### Recommended Improvements
- Add a real PHP linting and syntax-check workflow for all application files.
- Add unit and integration tests for important business flows.
- Add database setup scripts for CI validation.
- Introduce environment-based configuration for production use.
- Keep GitHub Pages deployment for documentation and final report publishing.

## User Guide

### How to Use the System
1. Open the application in a browser.
2. Register a new account or log in with existing credentials.
3. Add items if the item list is empty.
4. Create categories and define monthly budgets if needed.
5. Add expenses by entering date, item, cost, category, notes, and optional receipt.
6. Open the dashboard to review summaries and spending charts.
7. Use Manage Expenses to search, filter, edit, delete, or export records.
8. Use Recurring Expenses to automate regular payments.
9. Use the reporting pages to analyze spending by day, month, or year.
10. Update the profile page to set default currency and category.

### Main User Benefits
- Simple expense entry
- Better budget control
- Clear spending visibility
- Historical analysis
- Exportable records

## System Screens

### Figure 1. Login Page
This screen allows an existing user to enter an email address and password in order to access the system.

### Figure 2. User Registration Page
This screen allows a new user to create an account by entering full name, email address, mobile number, password, and repeated password. It is the entry point for new users who want to access the expense tracking features of the system.

### Figure 3. Dashboard Page
The dashboard gives a summarized overview of user spending. It presents key metrics such as today's expenses, last 7 days, current month, and total expense. It also includes charts and quick statistics to help the user understand spending behavior visually.

### Figure 4. Add Expense Page
This page is used to record a new expense. The user can select the date, currency, item, category, cost, notes, and upload a receipt file. It is one of the core input pages of the application.

### Figure 5. Manage Expenses Page
This screen displays stored expense records in tabular form. It supports searching, filtering, exporting to CSV, editing, and deleting expense entries. This page is important for reviewing and controlling previously entered data.

### Figure 6. Categories and Budgets Page
This page allows the user to create categories and assign monthly budgets. It also displays category performance by comparing spent amounts against budgeted amounts. This supports financial planning and budget control.

### Figure 7. Daily Report Page
This page presents analytical output for a selected date range. It includes total expense, number of active spending days, highest spending day, and graphical charts. It helps the user analyze short-term spending trends.

### Figure 8. Monthly Report Page
This page summarizes expenses by month and helps the user compare spending across longer time periods.

### Figure 9. Yearly Report Page
This page provides yearly analysis so the user can review long-term spending patterns across multiple years.

### Figure 10. Recurring Expenses Page
This page is used to define repeated expenses such as subscriptions or monthly bills. The user can choose item, amount, category, frequency, and start date. The system then automatically adds expenses when the due date arrives.

### Figure 11. User Profile Page
This page stores and updates user information such as name, email, mobile number, default currency, and default category. It also provides a way to personalize system behavior according to user preferences.

## Developer Guide

### Folder Overview
- `includes/`
  Shared database connection, layout includes, and helper functions
- `css/`, `js/`, `assets/`
  Frontend resources and styling
- Root PHP files
  Application pages for login, dashboard, expenses, reports, and user features
- `uploads/receipts/`
  Uploaded receipt files

### Important Files
- `includes/dbconnection.php`
  Creates the MySQL connection
- `includes/expense-helpers.php`
  Contains helper functions for schema updates, prepared statements, category and budget logic, recurring processing, and utility formatting
- `includes/report-helpers.php`
  Contains reporting utilities such as currency formatting and sanitization
- `dashboard.php`
  Main analytics and dashboard page
- `manage-expense.php`
  Expense listing, filters, delete operation, and CSV export
- `manage-categories.php`
  Category and budget management
- `manage-recurring.php`
  Recurring expense scheduling

### Developer Notes
- Newer pages use prepared statements and reusable helpers.
- Older pages still contain inline SQL and can be refactored.
- The system is file-based rather than MVC, so changes should be made carefully to avoid duplication.
- Shared logic should continue to be moved into helper files to improve maintainability.

## Self-evaluation

### Strengths
- The project solves a clear real-world problem.
- It supports much more than simple CRUD by including budgets, recurring expenses, receipt uploads, multi-currency handling, and visual reports.
- The dashboard offers meaningful analysis rather than only raw data entry.
- The helper-based structure in newer modules improves code reuse and maintainability.
- CSV export adds practical usefulness for users.

### Weaknesses
- Security is inconsistent across the codebase.
- Password hashing uses MD5 instead of stronger modern hashing algorithms.
- Some modules still use direct SQL queries, which increase security risk.
- There is no automated test suite.
- There is no formal migration system for schema evolution.
- The architecture is modular but not fully layered in an enterprise sense.

### Lessons Learned
This project demonstrates that software engineering is not only about making features work, but also about structuring the code, validating inputs, designing database relationships, and planning for maintainability. It also shows how a system can evolve over time from a simple procedural design into a more modular structure.

## Future Work

- Replace MD5 password hashing with `password_hash()` and `password_verify()`.
- Refactor all remaining direct SQL queries to prepared statements.
- Add role-based access control or admin monitoring if the system grows.
- Add automated tests for critical business flows.
- Introduce proper database migration scripts.
- Add pagination for large expense datasets.
- Add charts for category trends over time.
- Support PDF report export in addition to CSV.
- Add email-based password recovery.
- Build a REST API or mobile app version in the future.

## Conclusion
The Expense Tracker System addresses a practical problem and delivers a complete web-based solution with several useful modules. The system goes beyond basic data entry by including reporting, budgeting, recurring expenses, receipt management, and visual analytics. From a software engineering perspective, the project demonstrates requirements handling, modular design, database modeling, user-centered functionality, and iterative improvement. At the same time, it reveals valuable improvement opportunities in security, testing, and deployment automation. Overall, the project is functional, relevant, and suitable for web application engineering documentation.
