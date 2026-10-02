# Acceptance Test Checklist

| Requirement | Acceptance Procedure | Expected Result | Status |
| --- | --- | --- | --- |
| User registration | Register with valid full name, mobile number, email, and password. | A new user account is created. | Manual |
| Login and logout | Log in with valid credentials, then log out. | The user can access protected pages and end the session. | Manual |
| Add expense | Submit an expense with date, item, category, amount, and currency. | The expense is saved and displayed in the expense list. | Manual |
| Edit expense | Update the amount or category of an existing expense. | The edited values are saved correctly. | Manual |
| Delete expense | Delete an existing expense record. | The expense is removed from the list. | Manual |
| Search and filter | Filter expenses by text, category, date range, amount range, or currency. | Only matching records are displayed. | Manual |
| CSV export | Export filtered expenses. | A CSV file is downloaded. | Manual |
| Category management | Create a new category. | The category is available for expenses and budgets. | Manual |
| Budget management | Assign a monthly budget to a category. | Budget status appears in category and dashboard views. | Manual |
| Recurring expenses | Create a weekly or monthly recurring expense. | Due expenses are generated automatically. | Manual |
| Reports | Generate date-wise, month-wise, and year-wise reports. | Report summaries and charts are displayed. | Manual |
| Profile settings | Update default currency or category. | New defaults are stored and reused by the system. | Manual |
