# Expense Tracker System Report

This folder contains the GitHub Pages report site for the Expense Tracker System. The site uses top-level Markdown pages so the Just the Docs sidebar stays clean, and it is published through the existing `.github/workflows/report-site.yml` workflow.

## Structure

- `_config.yml` configures the Jekyll site.
- `index.md` is the report home page.
- Top-level `*.md` files contain the main report chapters used by the site sidebar.
- `project-quality.md` summarizes the project against the software engineering evaluation criteria.
- `pictures/` is reserved for screenshots used by the report.
- `diagrams/` is reserved for exported architecture, database, and use-case diagrams.

## Assets

- Screenshots can be stored in `pictures/`.
- Exported diagrams can be stored in `diagrams/`.
- The deployment URL is configured in `_config.yml`.
