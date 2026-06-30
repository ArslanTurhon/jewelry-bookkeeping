# Finance Repository Instructions

Before analyzing, editing, testing, reviewing, or deploying this repository:

1. Read and follow [`SKILL.md`](SKILL.md).
2. Invoke the relevant installed skills listed in its **Installed Skill Routing** section.
3. On the first Finance task after Codex or the machine starts, verify the local development environment before changing code:
   - run `git status --short --branch`;
   - check `php -v`, `composer --version`, `node -v`, and `npm -v`;
   - confirm `financeBackend/vendor/autoload.php`, `financeFrontend/node_modules`, and `financeBackend/.env` exist;
   - report missing or incompatible prerequisites before installing, migrating, or modifying anything.
4. Preserve unrelated working-tree changes and sensitive financial data.

When opening or testing webpages, use Google Chrome unless the user explicitly requests another browser. Prefer the installed `chrome:control-chrome` plugin skill when Chrome interaction depends on the user's existing tabs, login state, or extensions; use terminal Playwright only when browser automation does not need that existing Chrome state.
