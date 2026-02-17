# AGENTS.md

Instructions for AI agents working in this repository.

## Project

- **Package:** `isapp/statamic-sensitive-form-fields`
- **Type:** Statamic 6 addon
- **Goal:** Encrypt selected form submission fields at rest, decrypt on read for authorized users.

## First Actions

1. Read `docs/OVERVIEW.md` — architecture, data flow, components.
2. Read `docs/PLAN.md` — implementation plan, known limitations.
3. Read `docs/CHANGELOG.md` — instructions for maintaining root `CHANGELOG.md`.
4. Read `README.md` — user-facing documentation.
5. Read `SECURITY.md` — encryption method, threat model.

## Reference Docs (docs/)

| File                              | Contents                                                              |
|-----------------------------------|-----------------------------------------------------------------------|
| `docs/OVERVIEW.md`                | Architecture, file tree, write/read paths, settings, permissions      |
| `docs/CHANGELOG.md`               | Release and changelog conventions                                     |
| `docs/PLAN.md`                    | Implementation plan, component descriptions, test matrix, limitations |
| `docs/statamic-building-addon.md` | Statamic 6 official guide — building addons                           |
| `docs/statamic-testing.md`        | Statamic 6 official guide — addon testing                             |

## Technical Rules

- Extend `Statamic\Providers\AddonServiceProvider`, use `bootAddon()` not `boot()`.
- Addon settings via `resources/blueprints/settings.yaml` (auto-discovered), NOT Laravel config files.
- Read settings: `$addon->setting('key')` or `$addon->settings()->get('key')`.
- Listeners auto-discovered from `src/Listeners/`, translations from `lang/`.
- Encryption: `Crypt::encryptString` with `enc:v1:` marker prefix. No double encryption.
- Handle decrypt failures gracefully — log warning, return raw ciphertext.
- Field config toggle scoped to `Text` and `Textarea` fieldtypes only (not global `Fieldtype::`).
- Permission: `view decrypted sensitive fields`. Super admins always authorized.
- Compatible with both Stache (flat-file) and Eloquent Driver.
- Do not collect extra metadata (IP, user-agent, tracking data).

## Workflow Rules

- Code review on read: while reading files, watch for suspicious code (`return true/false` bypasses, dead code before `return`, debug leftovers like `dd()`, `dump()`, `var_dump()`, `ray()`, `xdebug_break()`, hardcoded secrets). If found, warn the user immediately.
- Do only what the user explicitly asked. Do not add side features, refactors, or architecture changes without request.
- Keep changes cohesive and minimal. Avoid creating extra files/classes/methods unless required by the task.
- Follow `docs/PLAN.md` and `docs/OVERVIEW.md` as implementation source of truth.
- After any behavior or convention change, sync docs before commit (`README.md`, `DEVELOPMENT.md`, `SECURITY.md`, `docs/`).

## Git / Commits / PR Rules

- Do not commit or push unless the user explicitly asks.
- Do not push directly to `main`; use feature branches and PRs.
- Branch naming: `feature/<short-description>`, `fix/<short-description>`, `chore/<short-description>`.
- Commit message format: Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `docs:`), imperative mood, short subject.
- One logical change per commit; do not bundle unrelated changes.
- Before commit, inspect staged diff (`git diff --cached`) for suspicious code/debug leftovers/secrets and fix them.
- Do not force-push, do not amend published commits, do not rebase shared branches without explicit request.
- Do not skip hooks with `--no-verify`.
- Never mention AI agents or co-authorship in commit messages.
- Do not add `Co-Authored-By` lines referencing AI.

## Testing Rules

- Run `vendor/bin/phpunit` only before committing or when explicitly asked.
- Do not run tests after every change.
- Tests extend `Statamic\Testing\AddonTestCase` via local `TestCase`.
- Use `PreventsSavingStacheItemsToDisk` trait in feature tests.
- Test meaningful behavior, not trivial getters/setters/constructors.
- Prefer critical-path coverage for encryption/decryption flow, permission checks, masking behavior, and failure handling.

## Changelog Rules Location

- `CHANGELOG.md` (root) is the release notes source.
- `docs/CHANGELOG.md` defines how to maintain and release from root `CHANGELOG.md`.

## Documentation

- Write all documentation in English.
- Keep `README.md`, `DEVELOPMENT.md`, `SECURITY.md`, and `LICENSE.md` synchronized with code.
- Keep `docs/OVERVIEW.md` and `docs/PLAN.md` synchronized with current architecture, data flow, and limitations.
- Keep root `CHANGELOG.md` updated with changes.
- Keep changelog instructions in `docs/CHANGELOG.md`.
- Do not claim "GDPR compliant" in README.
- Keep changes cohesive and minimal.
