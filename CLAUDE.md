# CLAUDE.md

## Scope
Instructions for Claude when working in this repository.

## Mandatory Documentation Rules
- Write all documentation in English.
- Keep docs synchronized with implementation changes.
- Use Claude memory (external to this repository) as persistent project memory.
- Do not create or keep `memory.md` in this repository.
- Update Claude memory whenever requirements, architecture, permissions, edge-case behavior, or test status changes.
- Always review and maintain these documentation files as needed: `README.md`, `DEVELOPMENT.md`, `SECURITY.md`, `LICENSE.md`.

## First Actions in a Session
1. Read `DEVELOPMENT.md`.
2. Read `README.md`, `SECURITY.md`, and `LICENSE.md` (if present).
3. Load current Claude memory context.
4. Reconcile differences between current code and all documentation.
5. Treat the newest explicit user instruction as source of truth, then update Claude memory.

## Missing Documentation Handling
- If `SECURITY.md` or `LICENSE.md` is missing, record this in Claude memory.
- Create or update missing docs when the current task requires security or license clarification.

## Project Intent
Build and maintain a free Statamic 6 addon named `Sensitive Form Fields` that:
- Encrypts selected form submission fields before persistence.
- Decrypts them on read for authorized users.
- Preserves encrypted-at-rest storage without rewriting storage during reads.

## Technical Expectations
- Use Laravel Crypt with `APP_KEY`.
- Use marker prefix `enc:v1:` for encrypted payload detection.
- Avoid double encryption.
- Handle decrypt failures gracefully and log warnings.
- Keep behavior compatible with both Stache and Eloquent submission storage.

## Security and Permissions
- Enforce permission `view decrypted sensitive fields` for plaintext reads.
- For users without permission, return non-decrypted output according to current project rule.
- Avoid collecting extra metadata (IP, user-agent, tracking data).

## Implementation Checklist
1. Sensitive per-field blueprint config toggle exists and is discoverable.
2. Sensitive handles are resolved from form blueprint definitions.
3. Write path encryption is applied before persistence.
4. Read path decryption is runtime-only and authorization-aware.
5. Non-sensitive fields are never transformed.

## Testing Expectations
- Validate encrypted storage marker presence for sensitive fields.
- Validate authorized plaintext reads.
- Validate unauthorized non-plaintext reads.
- Validate no double encryption.
- Validate non-sensitive plaintext retention.
- Do NOT run tests after every change. Only run tests before committing or when explicitly asked.

## Git Commits
- Never mention Claude or AI co-authorship in commit messages.
- Do not add `Co-Authored-By` lines referencing Claude.

## Ongoing Maintenance
- If behavior decisions change, record them immediately in Claude memory.
- Keep `README.md`, `DEVELOPMENT.md`, `SECURITY.md`, and `LICENSE.md` aligned with actual behavior and constraints.
