# AGENTS.md

Instructions for AI agents working in this repository.

## Project

- **Package:** `isapp/statamic-sensitive-form-fields`
- **Type:** Statamic 6 addon
- **Goal:** Encrypt selected form submission fields at rest, decrypt on read for authorized users.

## First Actions

1. Read `docs/OVERVIEW.md` — architecture, data flow, components.
2. Read `docs/PLAN.md` — implementation plan, known limitations.
3. Read `README.md` — user-facing documentation.
4. Read `SECURITY.md` — encryption method, threat model.

## Reference Docs (docs/)

| File | Contents |
|------|----------|
| `docs/OVERVIEW.md` | Architecture, file tree, write/read paths, settings, permissions |
| `docs/PLAN.md` | Implementation plan, component descriptions, test matrix, limitations |
| `docs/statamic-building-addon.md` | Statamic 6 official guide — building addons |
| `docs/statamic-testing.md` | Statamic 6 official guide — addon testing |

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

## Testing

- Run `vendor/bin/phpunit` only before committing or when explicitly asked.
- Do NOT run tests after every change.
- Tests extend `Statamic\Testing\AddonTestCase` via local `TestCase`.
- Use `PreventsSavingStacheItemsToDisk` trait in feature tests.

## Git Commits

- Never mention AI agents or co-authorship in commit messages.
- Do not add `Co-Authored-By` lines referencing AI.

## Documentation

- Write all documentation in English.
- Keep `README.md`, `DEVELOPMENT.md`, `SECURITY.md`, `LICENSE.md` synchronized with code.
- Do not claim "GDPR compliant" in README.
- Keep changes cohesive and minimal.
