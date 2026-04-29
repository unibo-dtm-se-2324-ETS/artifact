---
title: Developer Guide
nav_order: 11
---

# Developer Guide

## Folder Overview

- `includes/`
  Shared database connection, helpers, and common layout includes.
- `css/`, `js/`, `assets/`, `fonts/`
  Frontend styling, scripts, and media resources.
- Root PHP files
  Main application pages for authentication, dashboard, expense management, reporting, profile, and supporting features.
- `uploads/receipts/`
  Stores uploaded receipt files.
- `report/`
  Contains the project report site for GitHub Pages.

## Important Files

- `includes/dbconnection.php`
  Creates the MySQL connection.
- `includes/expense-helpers.php`
  Contains helper functions for validation, schema updates, categories, budgets, recurring processing, and formatting.
- `includes/report-helpers.php`
  Contains reporting-related utility functions.
- `dashboard.php`
  Main analytics and summary page.
- `manage-expense.php`
  Expense listing, filtering, deletion, and CSV export.
- `manage-categories.php`
  Category and budget management.
- `manage-recurring.php`
  Recurring expense creation and scheduling.
- `add-expense.php`
  Main expense entry workflow.
- `user-profile.php`
  User details and default preference management.

## Developer Notes

- The application is structured as file-based PHP rather than a formal MVC framework.
- Newer pages already use prepared statements and helper-based reuse more consistently.
- Runtime schema checks simplify local upgrades but are not a substitute for proper migrations.
- Future maintenance should continue moving duplicated SQL and business rules into shared helpers.
