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
| `docs/statamic-events.md`         | Statamic 6 official guide — all available events                      |

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
- At session start, if any doc inconsistencies or staleness are discovered during initial reading, fix them immediately — do not wait for user instruction.
- After adding a feature, fixing a bug, or changing any behavior or convention, sync the relevant docs before commit (see Documentation section for what to update and when).

## Artisan Command Rules

These rules apply whenever a new Artisan command is written or documented.

**Idempotency:** Every bulk-operation command must be safe to run multiple times. Before writing, think: "what happens on the second run?" Guard against double-processing — skip values already in the target state, never overwrite data that cannot be recovered.

**CLI secrets:** Never accept a production secret (APP_KEY, passwords, tokens) solely as a CLI argument — it leaks into shell history and `ps` output. Accept it via `$this->secret()` (hidden prompt) and make the CLI argument optional for non-interactive/CI use. Document both modes in README.

**Documentation flow correctness:** When documenting a command that depends on external state (e.g. a key that must be rotated first), trace the exact execution order step by step before writing. Then re-read the written steps as a user would and verify the sequence is correct. Check every location in the docs where the same flow is described — README intro, usage section, and Limitations — and make sure they all say the same thing.

**Doc cross-reference check:** After fixing a documentation error, grep for the incorrect phrase or concept across all `.md` files to catch duplicate occurrences before committing.

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
- PR body: include only a `## Summary` section (bullet points). Do not add a `## Test plan` section.

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
- Do not claim "GDPR compliant" in README.

### What to update and when

| Change type | Update these files |
|---|---|
| New feature | `CHANGELOG.md`, `README.md` (if user-facing), `docs/OVERVIEW.md`, `docs/PLAN.md` |
| Bug fix | `CHANGELOG.md` |
| Architecture change | `docs/OVERVIEW.md`, `docs/PLAN.md`, `README.md`, `DEVELOPMENT.md` |
| Security change | `SECURITY.md`, `CHANGELOG.md` |
| New/changed setting or permission | `README.md`, `docs/OVERVIEW.md` |
| New/changed Artisan command | `README.md`, `docs/PLAN.md` |
