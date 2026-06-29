# Finance Project Skill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add project guidance that routes finance development tasks to relevant installed skills.

**Architecture:** `SKILL.md` is the single source of project workflow guidance. `AGENTS.md` is the repository entry point and requires agents to load that guidance.

**Tech Stack:** Markdown, YAML frontmatter, Laravel 12/PHP 8.2, Vue 3/Vite

---

### Task 1: Add the project skill

**Files:**
- Create: `SKILL.md`

- [ ] Add valid `finance-project` frontmatter.
- [ ] Document structure, business safety rules, commands, and task-to-skill routing.

### Task 2: Add repository instructions

**Files:**
- Create: `AGENTS.md`

- [ ] Require reading `SKILL.md` before project work.
- [ ] Preserve the Google Chrome browser convention.

### Task 3: Verify

- [ ] Check frontmatter and relative references.
- [ ] Run `cd financeBackend && php artisan test`.
- [ ] Run `cd financeFrontend && npm run build`.
- [ ] Review `git diff` without changing unrelated work.
