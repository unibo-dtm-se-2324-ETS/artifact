---
title: Requirements
nav_order: 3
---

# Requirements

## Functional Requirements

1. The system shall allow users to register with full name, mobile number, email, and password.
2. The system shall allow registered users to log in and log out.
3. The system shall support forgot-password, reset-password, and change-password workflows.
4. The system shall allow users to update their profile and default settings.
5. The system shall allow users to add and manage expense items.
6. The system shall allow users to add expenses with date, item, amount, currency, category, notes, and optional receipt.
7. The system shall allow users to edit and delete existing expense records.
8. The system shall allow users to search and filter expenses by text, date range, amount range, category, and currency.
9. The system shall allow users to export filtered expense data as CSV.
10. The system shall allow users to manage categories and assign monthly budgets.
11. The system shall allow users to define recurring expenses with weekly or monthly frequency.
12. The system shall automatically create due recurring expenses in the main expense table.
13. The system shall display dashboard summaries such as today, weekly, monthly, yearly, and total expense.
14. The system shall present chart-based insights for monthly spending and top categories.
15. The system shall generate daily, monthly, and yearly expense reports.

## User Stories

| ID | User story | Related requirements |
| --- | --- | --- |
| US1 | As a new user, I want to create an account so that I can keep my expense data private and persistent. | FR1 |
| US2 | As a registered user, I want to log in and log out so that only I can access my expenses. | FR2 |
| US3 | As a user who forgot a password, I want to reset it so that I can recover access to my account. | FR3 |
| US4 | As a user, I want to update my profile and default preferences so that expense forms are faster to complete. | FR4 |
| US5 | As a user, I want to create reusable expense items so that repeated entries can be added consistently. | FR5 |
| US6 | As a user, I want to add, edit, and delete expenses so that my spending records stay accurate. | FR6, FR7 |
| US7 | As a user, I want to search, filter, and export expenses so that I can inspect or reuse selected data. | FR8, FR9 |
| US8 | As a user, I want to define categories and monthly budgets so that I can compare real spending with planned spending. | FR10 |
| US9 | As a user, I want to schedule recurring expenses so that predictable payments are not forgotten. | FR11, FR12 |
| US10 | As a user, I want dashboard summaries and charts so that I can understand my spending quickly. | FR13, FR14 |
| US11 | As a user, I want daily, monthly, and yearly reports so that I can analyze spending over different time periods. | FR15 |

## Definition of Done

| Requirement group | Definition of done |
| --- | --- |
| Account management | Registration, login, logout, password recovery, and password change pages validate input, update the database correctly, and protect private pages with sessions. |
| Profile and defaults | Profile changes are saved for the logged-in user and reused by relevant expense forms. |
| Expense items | Users can create and reuse item names without affecting other users' data. |
| Expense CRUD | Users can add, edit, and delete expenses with valid date, item, amount, currency, category, notes, and optional receipt data. Invalid input is rejected or handled safely. |
| Expense filtering and CSV export | Filters return only matching records, and CSV export includes the same filtered data in a downloadable file. |
| Categories and budgets | Categories and budgets can be created per user, displayed in management pages, and reflected in dashboard budget status. |
| Recurring expenses | Weekly and monthly recurring records can be created, stored, activated or deactivated, and converted into due expense entries. |
| Dashboard and charts | Summary totals and charts load for the logged-in user and use that user's expense data. |
| Reports | Date-wise, month-wise, and year-wise reports display correct totals, details, and charts for selected periods. |
| Acceptance testing | Each requirement group has a corresponding manual acceptance procedure in `tests/acceptance-checklist.md`. |

## Non-Functional Requirements

- Usability: The interface should be simple enough for ordinary users without technical knowledge.
- Performance: Common dashboard and report pages should load in acceptable time for personal-scale datasets.
- Maintainability: Shared logic should be reused through helper files and modular PHP pages.
- Reliability: The system should store and retrieve user expense data consistently during normal operation.
- Portability: The project should run in a standard XAMPP environment with PHP and MySQL.
- Security: Sessions should protect internal pages and user input should be validated before processing.

## Software Requirements

- Operating system: Windows
- Web server stack: XAMPP
- Server-side language: PHP
- Database: MySQL
- Frontend technologies: HTML, CSS, JavaScript, Bootstrap
- Chart library: Chart.js
- Browser: Chrome, Edge, or Firefox

## Hardware Requirements

- Personal computer or laptop
- Minimum 4 GB RAM
- Enough disk space for project files, database, and uploaded receipts
