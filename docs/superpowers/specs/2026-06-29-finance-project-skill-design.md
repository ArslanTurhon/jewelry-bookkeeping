# Finance Project Skill Design

## Goal

Add a project-local skill that combines repository development guidance with explicit routing to relevant installed Codex skills.

## Design

- Root `SKILL.md` uses standard skill frontmatter and documents the repository structure, commands, engineering rules, and deployment boundary.
- Root `AGENTS.md` requires agents to read and follow `SKILL.md` before project work.
- Skill routing is conditional: an agent invokes a named skill only when it is available in the current session and relevant to the task.
- Browser-based verification uses Google Chrome unless the user requests another browser.

## Validation

- Validate YAML frontmatter fields and naming.
- Confirm every path and command against the repository.
- Confirm `AGENTS.md` links to the root skill using a relative path.
- Run backend tests and the frontend production build.
