---
title: CI/CD
nav_order: 9
---

# CI/CD

## Current Status

The repository includes GitHub Actions workflows for packaging and report-site publishing, along with a PHP Composer workflow file.

## Workflows Present in the Repository

- `main.yml`
  Runs PHP syntax checks on application files.
- `artifact.yml`
  Builds a zipped release artifact from the repository contents.
- `report-site.yml`
  Builds the `report/` folder with Jekyll and deploys it to GitHub Pages.
- `php.yml`
  Validates Composer configuration and installs Composer dependencies in CI. The project currently defines `composer.json`, while `composer.lock` is generated only after dependency installation.

## Assessment

The project has an initial CI/CD setup for packaging and documentation publishing. However, the application pipeline is not yet fully aligned with the current codebase because:

- there is no automated test suite for the PHP application,
- there is no Composer project file in the repository,
- there is no database migration or seed step in CI,
- there are no smoke tests for deployed behavior.

## Recommended Improvements

- Add a real PHP linting and syntax-check workflow for all application files.
- Add unit and integration tests for important business flows.
- Add database setup scripts for CI validation.
- Introduce environment-based configuration for production use.
- Keep GitHub Pages deployment for documentation and final report publishing.
