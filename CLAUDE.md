# CLAUDE.md

Claude-specific instructions. General agent rules are in `AGENTS.md`.

## Session Start

1. Read `AGENTS.md` for project rules and reference docs.
2. Load Claude memory context.
3. Reconcile differences between code and documentation.
4. Treat newest user instruction as source of truth, then update Claude memory.

## Claude Memory

- Use Claude memory (external to this repository) as persistent project memory.
- Do not create or keep `memory.md` in this repository.
- Update memory when requirements, architecture, or behavior changes.
