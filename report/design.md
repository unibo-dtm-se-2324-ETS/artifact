---
title: Design
nav_order: 4
---

# Design

## System Architecture

The project follows a modular PHP web application structure with three main layers:

1. Presentation layer
   PHP pages render forms, tables, dashboard cards, and charts using HTML, CSS, JavaScript, and Bootstrap.
2. Logic layer
   Shared behavior is handled through helper files such as `includes/expense-helpers.php` and `includes/report-helpers.php`.
3. Data layer
   The application uses MySQL through a shared connection file, `includes/dbconnection.php`.

## Architecture Overview

```text
+--------------------+
|   Web Browser      |
|  (User Interface)  |
+---------+----------+
          |
          v
+--------------------+
| PHP Application    |
| - Auth pages       |
| - Dashboard        |
| - Expense pages    |
| - Reports          |
+---------+----------+
          |
          v
+--------------------+
| Shared Helpers     |
| - Validation       |
| - Formatting       |
| - CSRF handling    |
| - Recurring logic  |
| - Schema helpers   |
+---------+----------+
          |
          v
+--------------------+
| MySQL Database     |
| - tbluser          |
| - tblexpense       |
| - tblitems         |
| - tblcategories    |
| - tblbudgets       |
| - tblrecurring     |
+--------------------+
```

## Website Navigation Chart

```text
+-------------------+
| Login / Register  |
+---------+---------+
          |
          v
+-------------------+
|    Dashboard      |
+----+---+---+---+--+
     |   |   |   |
     |   |   |   +------------------------+
     |   |   |                            |
     |   |   v                            v
     |   |  +-------------------+   +-------------------+
     |   |  |  Manage Expense   |<->|   Add / Edit      |
     |   |  +-------------------+   |     Expense       |
     |   |           |              +-------------------+
     |   |           v
     |   |  +-------------------+
     |   |  | CSV Export /      |
     |   |  | Filter Results    |
     |   |  +-------------------+
     |   |
     |   +---------------------> +-------------------+
     |                            | Categories &      |
     |                            | Budgets           |
     |                            +-------------------+
     |
     +--------------------------> +-------------------+
     |                            | Recurring         |
     |                            | Expenses          |
     |                            +-------------------+
     |
     +--------------------------> +-------------------+
     |                            | Reports           |
     |                            | Day / Month / Year|
     |                            +-------------------+
     |
     +--------------------------> +-------------------+
                                  | User Profile      |
                                  +-------------------+
```

This chart reflects the main website navigation flow. After authentication, the dashboard acts as the central hub and connects the user to expense management, budgeting, recurring scheduling, reports, and profile settings.

## Main Modules

- Authentication module
  Handles registration, login, password reset, and password change.
- Dashboard module
  Displays expense summaries, budget health, recent activity, and charts.
- Expense management module
  Supports adding, editing, deleting, filtering, and exporting expenses.
- Item management module
  Maintains reusable expense item names.
- Category and budget module
  Stores categories and monthly budget values.
- Recurring expense module
  Automates weekly and monthly expense creation.
- Reporting module
  Produces date-wise, month-wise, and year-wise reports.
- Profile module
  Stores user information and default preferences.

## Database Design

The main database tables used by the system are:

### `tbluser`

- `ID`
- `FullName`
- `MobileNumber`
- `Email`
- `Password`
- `RegDate`
- `DefaultCurrency`
- `DefaultCategoryId`

### `tblexpense`

- `ID`
- `UserId`
- `ExpenseDate`
- `ExpenseItem`
- `ExpenseCost`
- `Currency`
- `CategoryId`
- `Notes`
- `ReceiptPath`
- `CreatedAt`

### `tblitems`

- `ID`
- `UserId`
- `ItemName`
- `CreatedAt`

### `tblcategories`

- `ID`
- `UserId`
- `CategoryName`
- `CreatedAt`

### `tblbudgets`

- `ID`
- `UserId`
- `CategoryId`
- `BudgetMonth`
- `Currency`
- `BudgetAmount`
- `CreatedAt`
- `UpdatedAt`

### `tblrecurring`

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

## Data Relationships

```text
tbluser (1) -------- (many) tblexpense
tbluser (1) -------- (many) tblitems
tbluser (1) -------- (many) tblcategories
tbluser (1) -------- (many) tblbudgets
tbluser (1) -------- (many) tblrecurring

tblcategories (1) -- (many) tblexpense
tblcategories (1) -- (many) tblbudgets
tblcategories (1) -- (many) tblrecurring
```

## Input Design

The system uses form-based input for registration, login, profile updates, expense creation, expense filtering, budget entry, recurring schedules, and report queries. Input controls include text boxes, password fields, date inputs, numeric fields, select menus, text areas, and file-upload controls.

## Output Design

The system produces several output types:

- Dashboard summary cards
- Filtered expense tables
- Budget status indicators
- CSV exports
- Date-wise, month-wise, and year-wise report pages
- Charts that show spending distribution and trends
