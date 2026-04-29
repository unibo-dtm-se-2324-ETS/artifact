---
title: Validation
nav_order: 6
---

# Validation

## Testing Approach

The repository does not include a full automated test suite for application behavior, so validation is mainly manual. Even so, manual testing can be structured around the most important user workflows and output checks.

## Sample Test Cases

| Test Case | Input | Expected Result |
| --- | --- | --- |
| User registration | Valid name, email, mobile number, password | New user account is created |
| Login | Correct email and password | User is redirected to dashboard |
| Add expense | Valid date, item, category, amount | Expense is saved successfully |
| Edit expense | Updated amount or category | Existing expense data is updated |
| Delete expense | Existing expense selected | Expense is removed from the list |
| Add category | Unique category name | Category is stored successfully |
| Save budget | Category, month, amount | Budget appears in category and dashboard views |
| Add recurring expense | Weekly or monthly schedule | Future due expense is auto-generated |
| Generate report | Valid date or month range | Report summary and charts are displayed |
| Export CSV | Export action on filtered expenses | CSV file downloads correctly |

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
- Automated application tests are not yet included.

## Suggested Validation Improvements

- Add a repeatable test checklist for all main workflows.
- Introduce unit tests for helper functions.
- Add integration tests for login, expense creation, recurring processing, and report generation.
- Add security-focused validation for authentication and file uploads.
