---
title: Project Quality
nav_order: 14
---

# Project Quality

This chapter maps the project against the software engineering assessment criteria. The goal is to make clear which criteria are satisfied by the current repository, which ones are partially satisfied, which ones are not applicable to this project type, and which ones remain future work.

## Evaluation Summary

| Category | Current status | Evidence |
| --- | --- | --- |
| DVCS | Satisfied | The project is managed with Git and prepared for publication on GitHub. The repository stores both source code and report pages. |
| Versioning | Partially satisfied | Semantic Versioning is defined for releases. The current PHP page routes are not a versioned Web API. |
| Project structure | Partially satisfied | The repository has clear folders for helpers, assets, uploads, report pages, workflows, and acceptance tests. The PHP pages are still mostly stored at the project root. |
| Dependencies | Satisfied for the current scope | `composer.json` specifies PHP as a runtime dependency and provides a lint script. No external PHP packages are currently required. |
| Licensing | Satisfied | The repository includes an MIT `LICENSE`, and the motivation is documented below. |
| API | Not applicable | The system is a server-rendered PHP application and does not expose a public Web API. |
| Testing | Partially satisfied | Manual acceptance tests are documented. Automated tests and coverage measurement are future work. |
| Build | Satisfied | GitHub Actions run PHP syntax checks, and Composer provides a local lint command. |
| Deploy | Partially satisfied | Local XAMPP deployment and GitHub Pages report deployment are documented. Application package publishing to a language package repository is not applicable for this educational PHP web app. |
| DevOps | Partially satisfied | CI/CD exists for syntax checks, artifact packaging, and report deployment. Automated behavioral testing is not yet included. |
| DDD | Partially satisfied | Domain concepts and bounded context are identified. The implementation is procedural PHP rather than full DDD or hexagonal architecture. |
| Implementation | Satisfied for code-realisation criteria | The project implements a working server-rendered web client with PHP pages, forms, dashboards, reports, and database persistence. It does not implement microservices. |
| Modelling | Partially satisfied | Architecture, navigation, database, relationship, data-flow, state, sequence, and flow-chart models are documented in text form. Exported image diagrams are future work. |
| Requirements | Satisfied | Functional and non-functional requirements, user stories, and definitions of done are documented in the Requirements chapter. |
| Presentation | Satisfied in report form | The report is split into clear chapters and can be published as a GitHub Pages site. Oral exposition is outside the repository and must be evaluated during discussion. |

## DVCS

The project uses Git for version control and is structured for publication on GitHub. The repository includes both the source code and the report pages, so the implementation and documentation can evolve together.

Recommended repository conventions:

- Use Conventional Commits, such as `feat: add recurring expense page` or `fix: validate receipt uploads`.
- Keep each commit focused on one coherent change.
- Use a simple branching convention with `main` for stable work and short-lived feature branches for new changes.

Commit coherence should be maintained by grouping only related source, report, and workflow changes in the same commit. For example, a feature commit should include the related PHP page/helper edits and documentation updates for that feature, but not unrelated formatting or report rewrites.

## Versioning

The current application does not expose a public Web API, so OpenAPI or Swagger versioning is not required. Application routes are page-based PHP routes rather than versioned API routes.

Releases should follow Semantic Versioning:

- `MAJOR` for incompatible changes
- `MINOR` for new backward-compatible features
- `PATCH` for bug fixes and small improvements

The release artifact workflow can package a versioned release zip when a stable version is prepared.

## Project Structure

The repository contains the main PHP application files, shared helpers, frontend assets, upload storage, database scripts, report pages, and GitHub Actions workflows.

- Main application pages are stored as root PHP files.
- Shared code is stored in `includes/`.
- Frontend resources are stored in `css/`, `js/`, `assets/`, and `fonts/`.
- Uploaded receipts are stored in `uploads/receipts/`.
- Report documentation is stored in `report/`.
- Acceptance testing documentation is stored in `tests/`.
- Project dependencies and scripts are described in `composer.json`.

The project contains a formal dependency specification through `composer.json`. It declares the required PHP version and a lint script that can be used locally or in automation. The current project has no external runtime packages and no development packages, so `require-dev` is intentionally empty.

The structure is suitable for a small educational PHP project, although a larger production system could separate source files into a dedicated `src/` directory and automated tests into a full test suite under `tests/`.

## Licensing

The project includes an MIT License. This license was selected because it is simple, widely used, and suitable for educational and open-source projects. It allows reuse, modification, and distribution while keeping the copyright notice and license terms with the project.

## API

The current system is a server-rendered PHP web application and does not provide a separate public Web API. User actions are handled through web pages and form submissions. If a REST API is added in the future, it should be documented with OpenAPI and versioned from the first public release.

## Testing

The project currently relies mainly on manual validation. An acceptance checklist is included in `tests/acceptance-checklist.md` and maps important requirements to manual testing procedures. The Validation chapter reports the current validation results and quality observations.

Automated unit and integration tests are not yet implemented, so automated coverage is not available. Future testing work should introduce PHPUnit or a similar PHP testing framework, then report coverage results for helper functions, authentication flows, expense management, reporting, and recurring expense logic.

## Build

The repository includes a GitHub Actions workflow for PHP syntax checking. This provides an automatic build-quality check for PHP files on push and pull request events. The local equivalent is:

```bash
composer run lint
```

## Deploy

The application is deployed locally through XAMPP by copying the project into `htdocs`, importing the database, and opening the project through `localhost`. The report site can be published through GitHub Pages using the repository workflow.

For this project, publishing to a package repository such as Packagist is not the most suitable deployment target because the deliverable is a complete PHP/MySQL web application rather than a reusable PHP library. A zip artifact generated by the repository workflow is more appropriate for sharing the application source and deployment package.

## DevOps

The repository includes GitHub Actions workflows for:

- PHP syntax checking
- release artifact packaging
- report site publishing

The next improvements would be automated test execution, database setup in CI, and deployment checks for the application itself.

## Domain-Driven Design

The main domain is personal expense management. The bounded context includes expenses, categories, budgets, recurring schedules, reports, and user preferences.

Important domain concepts include:

- User
- Expense
- Expense item
- Category
- Budget
- Recurring expense
- Report

The current implementation is procedural PHP with helper functions rather than a formal DDD or hexagonal architecture. However, the domain concepts are clear and can be refactored into entities, repositories, and services in a future version.

Possible DDD mapping:

| DDD concept | Project equivalent |
| --- | --- |
| Entity | User, Expense, Category, Budget, RecurringExpense |
| Value object | Currency, Money amount, Date range, Frequency |
| Aggregate root | User as owner of expenses, categories, budgets, and recurring schedules |
| Repository | Database access currently implemented directly through PHP pages and helper functions |
| Service | Report generation, recurring expense generation, CSV export, validation, and formatting helpers |
| Factory | Not formally implemented; form-handling code currently creates records directly |

## Modelling

The report includes architecture, navigation, database, relationship, state, sequence, data-flow, and flow-chart models in text form. These models describe the main system structure and data relationships. Future work could add exported UML images in `report/diagrams/`.

## Requirements

Functional and non-functional requirements are documented in the Requirements chapter. User workflows are described in the User Guide and acceptance procedures are listed in `tests/acceptance-checklist.md`.

The definition of done for each core requirement is that the related page or function works correctly, stores or retrieves the expected data, validates user input, and passes the acceptance procedure defined for that requirement.

## Time

The exact submission date is outside the repository content and depends on the course schedule. The report should be submitted in the first useful session available after the course, and in any case within one year from the end of the course.

## Presentation

Report clarity is supported by separating the documentation into focused chapters and publishing it as a navigable GitHub Pages site. Code clarity is supported by descriptive PHP page names, shared helper files, and a root README. Oral exposition will be assessed during the final discussion with the teacher.
