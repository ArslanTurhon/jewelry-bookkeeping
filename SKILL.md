---
name: finance-project
description: Use when developing, debugging, testing, reviewing, or deploying the finance repository and its Laravel API or Vue administration interface.
---

# Finance Project

## Purpose

Use this file as the working guide for the finance repository. Keep changes scoped, preserve financial data integrity, and verify behavior at the API and UI boundaries.

## Repository Layout

- `financeBackend/`: Laravel 12 API, authentication, financial calculations, migrations, seeders, and PHPUnit feature tests.
- `financeFrontend/`: Vue 3 administration interface using Vite, Element Plus, Pinia, Axios, and ECharts.
- `financeBackend/public/`: production frontend assets served by Laravel. Update these only from a verified frontend build when deployment work requires it.
- `BAOTA_DEPLOY.md`: current BaoTa/Nginx deployment procedure.
- `backups/` and `release/`: database and deployment artifacts. Do not modify or delete them unless explicitly requested.

## Installed Skill Routing

Before acting, inspect the skills available in the current session. Invoke every relevant skill below when it is installed:

- New features or behavior changes: `superpowers:brainstorming`, then `superpowers:writing-plans` and `superpowers:test-driven-development`.
- Bugs, failed tests, or unexpected behavior: `superpowers:systematic-debugging`.
- Browser interaction or UI verification: `browser:control-in-app-browser`; use Google Chrome unless the user specifies another browser.
- Browser automation from the terminal: `playwright` or `playwright-interactive` when persistent inspection is needed.
- Data, SQL, or statistical analysis: `data-analyst`.
- Completion claims: `superpowers:verification-before-completion`.
- Major completed changes: `superpowers:requesting-code-review`.

Do not invoke unavailable skills or use a skill merely because it is listed. Follow each invoked skill's own instructions.

## Development Rules

1. Read `git status` before editing and preserve unrelated user changes.
2. Treat transaction amounts, payment splits, gold weights, inventory, opening balances, and statistics as financial invariants.
3. For behavior changes, add or update a focused feature test first and observe the expected failure before implementation.
4. Keep validation and financial calculations authoritative in the backend. The frontend may guide input but must not be the sole enforcement layer.
5. Keep API field names and enums synchronized across migrations, models, controllers, statistics, dictionaries, tests, and frontend forms.
6. Make migrations compatible with supported databases. Guard MySQL-only SQL when tests run on SQLite.
7. Never expose `.env` secrets, tokens, passwords, production database contents, or backup data.
8. Do not rewrite generated assets, releases, backups, or deployment files unless they are in scope.

## Commands

Run backend checks:

```bash
cd financeBackend
php artisan test
```

Run backend formatting when PHP source changes:

```bash
cd financeBackend
./vendor/bin/pint --test
```

Run the frontend locally:

```bash
cd financeFrontend
npm run dev
```

Build the frontend:

```bash
cd financeFrontend
npm run build
```

## Verification

- Run the smallest relevant test during development, then the complete backend suite.
- Run the frontend production build for frontend changes.
- For UI changes, verify the affected flow in Google Chrome at the actual local URL.
- For API changes, verify authorization, validation failures, successful writes, and resulting balances/statistics.
- Before reporting completion, inspect the final diff and report warnings or unverified areas.

## Versioning and Rollback

- `VERSION` contains the current semantic version.
- Stable local versions use an exact Git tag such as `0.1.0`.
- Before creating a version, commit all intended changes, run verification, and create both a Git bundle and a project snapshot outside the repository.
- When the user requests “回滚到 x.x.x 版本”, first confirm that the matching tag and backup exist. Explain which commits and uncommitted changes would be affected before performing any destructive rollback.
- Preserve the current state in a safety branch or backup before changing the working tree to an older version.
