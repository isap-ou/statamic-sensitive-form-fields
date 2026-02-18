# Implementation Plan — Sensitive Form Fields

## Architecture Overview

```
ServiceProvider (bootAddon)
├── Register permission "view decrypted sensitive fields"
├── Append config toggle "sensitive" to Text + Textarea fieldtypes
├── Listener auto-discovered: SubmissionSaving → encrypt before write
└── Bind decorated SubmissionRepository → decrypt on read
```

Data flow:
```
[User submits form]
  → FormSubmitted (Statamic validates)
  → SubmissionSaving listener
    → resolve sensitive handles from form blueprint
    → encrypt unmarked values via Crypt::encryptString, add "enc:v1:" prefix
    → skip already-prefixed values (no double encryption)
  → Stache/Eloquent persists encrypted data to disk/DB

[Admin reads submission]
  → DecryptingSubmissionRepository::find() / whereForm() / all()
    → delegates to original repository
    → checks user permission "view decrypted sensitive fields" (global) or "view decrypted {form} sensitive fields" (per-form)
    → if authorized: strips "enc:v1:" prefix, decrypts via Crypt::decryptString
    → if unauthorized: returns masked value "••••••"
    → if decrypt fails: logs warning, returns raw ciphertext
```

---

## File Tree

```
src/
├── ServiceProvider.php                          ← wires permission, field config, repository decorator
├── Commands/
│   ├── EncryptExistingCommand.php               ← [PRO] bulk-encrypt existing submissions
│   ├── DecryptExistingCommand.php               ← [PRO] bulk-decrypt existing submissions
│   └── RekeyCommand.php                         ← [PRO] re-encrypt from old APP_KEY to current
├── Encryption/
│   └── FieldEncryptor.php                       ← encrypt/decrypt + marker logic + addon settings
├── Listeners/
│   └── EncryptSensitiveFields.php               ← SubmissionSaving listener (auto-discovered)
├── Repositories/
│   ├── DecryptingSubmissionRepository.php       ← decorator (find/whereForm/all/query)
│   ├── DecryptingSubmissionQueryBuilder.php     ← decorator wrapping query() results
│   └── RawSubmissionRepository.php              ← marker interface for undecorated repo
└── Support/
    └── SensitiveFieldResolver.php               ← reads blueprint, returns sensitive handles

resources/
└── blueprints/
    └── settings.yaml                ← addon settings (auto-discovered)

lang/
└── en/
    └── messages.php                 ← translations (auto-discovered)

tests/
├── TestCase.php
├── Unit/
│   └── FieldEncryptorTest.php       ← encrypt/decrypt/marker unit tests
└── Feature/
    └── SensitiveFieldsTest.php      ← integration tests
```

---

## Components

### FieldEncryptor
- `encrypt(string $value): string` — `enc:v1:` + `Crypt::encryptString()`. Skips if already prefixed.
- `decrypt(string $value): string` — strips prefix, decrypts. On failure: logs warning, returns raw.
- `isEncrypted(string $value): bool` — checks prefix.
- `isEnabled(): bool` — reads addon setting `enabled` (default true).
- `mask(): string` — reads addon setting `mask` (default `••••••`).
- Receives `Addon` instance via DI from ServiceProvider.

### SensitiveFieldResolver
- `resolve(Form $form): array` — iterates blueprint fields, returns handles where `sensitive === true`.
- Caches per form handle.

### EncryptSensitiveFields listener
- Listens to `SubmissionSaving` (auto-discovered from `src/Listeners/`).
- Resolves sensitive handles, encrypts string values, skips null/empty/non-string.

### DecryptingSubmissionRepository decorator
- Implements `SubmissionRepository`.
- Wraps original repository.
- Read methods (`find`, `whereForm`, `whereInForm`, `all`): post-process with decrypt/mask.
- `query()`: returns `DecryptingSubmissionQueryBuilder` wrapping the inner query builder.
- Write methods (`save`, `delete`, `make`): delegate directly.

### DecryptingSubmissionQueryBuilder decorator
- Implements `SubmissionQueryBuilderContract`.
- Wraps the inner query builder via `__call` proxy for fluent builder methods.
- `get()` and `paginate()`: post-process results with the same decrypt/mask logic.

### RawSubmissionRepository marker interface
- Extends `SubmissionRepository`, used as a typed container key.
- Bound via `app()->instance()` inside `ServiceProvider::decorateRepository()` to the original (undecorated) repository.
- Resolved by PRO commands to read and write raw submission data, bypassing the decorator and `SubmissionSaving` event.

### ServiceProvider
- `register()`: singleton bindings for FieldEncryptor (with Addon DI) and SensitiveFieldResolver.
- `bootAddon()`: permission, field config (Text + Textarea only), repository decorator.
- Listener, settings, translations: all auto-discovered.

### Addon Settings (`resources/blueprints/settings.yaml`)
- `enabled` (toggle, default true)
- `mask` (text, default `••••••`)

---

## FREE vs PRO

Edition is detected via the **Statamic Editions API**: `Addon::edition()` reads from `config('statamic.editions.addons.isapp/statamic-sensitive-form-fields')`. Declared in `composer.json` as `"editions": ["free", "pro"]`. Default (unconfigured) is `"free"`.

### FREE
- Encryption at rest: sensitive fields encrypted before storage.
- All CP users see decrypted values — no permission check.
- No masking. No PRO commands.

### PRO
- Permission-based access control: only super admins and users with `view decrypted sensitive fields` (global) or `view decrypted {form-handle} sensitive fields` (per-form) see decrypted values.
- Unauthorized users see mask string (default `••••••`, configurable in addon settings).
- Permission registered in CP only when PRO mode is active.
- PRO Artisan commands available: `sensitive-fields:encrypt-existing`, `sensitive-fields:decrypt-existing`, `sensitive-fields:rekey`.

---

## PRO Plan

### Recursive re-save commands (existing submissions)

**Implemented.** PRO-only Artisan commands for bulk migration of historical submissions.

1. `sensitive-fields:encrypt-existing` (`src/Commands/EncryptExistingCommand.php`)
   - Recursively iterates all forms and all existing submissions.
   - Resolves sensitive handles from each form blueprint.
   - Encrypts only unmarked plaintext values (`enc:v1:` guard prevents double encryption).
   - Persists via raw (undecorated) repository `save()` — bypasses the `SubmissionSaving` listener.

2. `sensitive-fields:decrypt-existing` (`src/Commands/DecryptExistingCommand.php`)
   - Recursively iterates all forms and all existing submissions.
   - Resolves sensitive handles from each form blueprint.
   - Decrypts values marked with `enc:v1:`.
   - Persists via raw (undecorated) repository `save()` — decrypted values are not re-encrypted.

3. `sensitive-fields:rekey` (`src/Commands/RekeyCommand.php`)
   - Recursively iterates all forms and all existing submissions.
   - Resolves sensitive handles from each form blueprint.
   - Decrypts values marked with `enc:v1:` using the provided old key (`--old-key`).
   - Re-encrypts with the current `APP_KEY` via `FieldEncryptor::encrypt()`.
   - Persists via raw (undecorated) repository `save()`.
   - On decryption failure (wrong old key for a value), warns and continues — data is never silently overwritten.

### Command behavior

- Works for both Stache and Eloquent driver.
- Idempotent runs (safe to execute repeatedly).
- Graceful per-submission error handling (warn + continue).
- Summary output: processed / updated / skipped / errors.
- `--form=<handle>` filter to target a single form.
- `--dry-run` option to preview changes without writing.
- Commands are registered only when the addon is in PRO mode (`bootAddon` checks `edition() === 'pro'`).

---

## Tests

### Unit (FieldEncryptorTest, 7 tests)
1. Encrypts value with marker prefix
2. Decrypts back to plaintext
3. No double encryption
4. Failed decrypt returns raw + logs warning
5. isEncrypted detects prefix
6. mask returns configured value
7. decrypt returns non-encrypted as-is

### Feature (SensitiveFieldsTest, 12 tests)
1. Sensitive field stored encrypted
2. Non-sensitive field remains plain
3. Already-encrypted value not double-encrypted
4. Free mode — all users see decrypted value
5. Free mode — super admin sees decrypted value
6. Pro mode — super admin reads plaintext
7. Pro mode — unauthorized user reads masked value
8. Pro mode — user with permission reads plaintext
9. Query builder decrypts for super admin in free mode
10. Query builder masks for unauthorized user in pro mode
11. Pro mode — per-form permission grants access to that form
12. Pro mode — per-form permission is scoped to that form only

### Feature PRO (ProCommandsTest, 6 tests)
1. encrypt-existing encrypts plaintext sensitive fields
2. encrypt-existing skips already-encrypted fields
3. encrypt-existing dry-run does not persist
4. decrypt-existing decrypts encrypted sensitive fields
5. decrypt-existing skips plaintext sensitive fields
6. decrypt-existing dry-run does not persist

---

## Known Limitations

1. **Search/filtering** — encrypted fields cannot be searched (ciphertext is opaque)
2. **APP_KEY rotation** — makes existing data unreadable without migration; use `sensitive-fields:rekey --old-key=<key>` (PRO) to re-encrypt with the new key
3. **Complex field types** — only string values encrypted; arrays/grids/replicator skipped
4. **Export** — decrypted or masked based on user permission
5. **API access** — encrypted/masked unless user has permission

---

## Roadmap

### [PRO] `sensitive-fields:rekey` — Re-key on APP_KEY rotation ✅ planned for next release

**Status:** Implemented.

Artisan command to re-encrypt all sensitive field values from an old `APP_KEY` to the current one.
Solves the documented APP_KEY rotation limitation.

- `--old-key=<base64:...>` — previous APP_KEY in `.env` format (required)
- `--form=<handle>` — target a single form
- `--dry-run` — preview without writing
- Skips plaintext values (not encrypted); warns and continues on decrypt failure.
- Implemented in `src/Commands/RekeyCommand.php`.

---

### [PRO] Per-form permission granularity — Implemented

Larger teams need per-form control (e.g. HR form vs. contact form handled by different roles).

- Global permission `view decrypted sensitive fields` acts as a wildcard across all forms (backward-compatible).
- Per-form permission `view decrypted {form-handle} sensitive fields` grants access to a single form only.
- Dynamic per-form permissions registered via Statamic's native `{placeholder}` + `replacements()` mechanism in `ServiceProvider::registerPermission()`.
- Both `DecryptingSubmissionRepository` and `DecryptingSubmissionQueryBuilder` check global then per-form permission via `isAuthorizedForForm(string $formHandle)`.

---

### [FREE/PRO] CP notification on decrypt failure — Planned

Currently, decryption failures (e.g. after an unrecovered APP_KEY rotation) are only logged via `Log::warning`. A Statamic CP notification dispatched to super admins would make data corruption visible without requiring log monitoring.

Implementation sketch:
- Hook into the existing `catch (\Throwable)` path in `FieldEncryptor::decrypt()`.
- Dispatch a Statamic `Notification` to super admins (or use a Statamic flash/CP alert).
- Add a rate-limit or deduplication guard to avoid notification spam.
