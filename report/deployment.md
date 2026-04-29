---
title: Deployment
nav_order: 8
---

# Deployment

## Deployment Environment

The application is designed for deployment on a local Apache and MySQL environment such as XAMPP.

## Deployment Steps

1. Install XAMPP on the target computer.
2. Copy the project folder into the `htdocs` directory.
3. Start Apache and MySQL from the XAMPP control panel.
4. Create the required MySQL database.
5. Update the database connection in `includes/dbconnection.php` if needed.
6. Import the base database structure if an SQL file is available.
7. Open the project in a browser using `http://localhost/Expense-Tracker-System/`.

## Deployment Notes

- Some additional tables and columns are created automatically by helper functions when relevant pages are opened.
- Uploaded receipts are stored inside the project folder, so the deployment environment must allow file uploads.
- The report site itself can be deployed separately through GitHub Pages using the repository workflow.

## Local Use Case

This deployment model is suitable for demonstration, lab presentation, and personal portfolio use because it requires only a local PHP and MySQL setup.
