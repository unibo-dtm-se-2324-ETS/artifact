---
title: Self-evaluation
nav_order: 12
---

# Self-evaluation

## Strengths

- The project solves a practical real-world problem.
- It includes more than simple CRUD by supporting budgets, recurring expenses, receipt uploads, multi-currency tracking, and analytical dashboards.
- The reporting features demonstrate useful data processing and output generation.
- Shared helper functions improve reuse and maintainability in newer modules.
- CSV export increases the practical usefulness of the system.

## Weaknesses

- Security practices are not yet fully consistent across the project.
- Older authentication code still uses MD5 password hashing.
- Some older modules still rely on direct SQL queries.
- Automated testing is limited.
- Database evolution is handled through runtime helper logic instead of a dedicated migration process.
- The architecture is modular but still largely procedural.

## Lessons Learned

This project shows that software engineering is not only about making features work. It also involves structuring the codebase, designing database relationships, validating user input, planning for maintainability, and documenting trade-offs. The system grew from a basic CRUD idea into a richer application with reporting and automation features.
