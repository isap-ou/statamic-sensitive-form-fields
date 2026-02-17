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
    → checks user permission "view decrypted sensitive fields"
    → if authorized: strips "enc:v1:" prefix, decrypts via Crypt::decryptString
    → if unauthorized: returns masked value "••••••"
    → if decrypt fails: logs warning, returns raw ciphertext
```

---

## File Tree

```
src/
├── ServiceProvider.php              ← wires permission, field config, repository decorator
├── Encryption/
│   └── FieldEncryptor.php           ← encrypt/decrypt + marker logic + addon settings
├── Listeners/
│   └── EncryptSensitiveFields.php   ← SubmissionSaving listener (auto-discovered)
├── Repositories/
│   └── DecryptingSubmissionRepository.php  ← decorator
└── Support/
    └── SensitiveFieldResolver.php   ← reads blueprint, returns sensitive handles

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
- Write methods (`save`, `delete`, `make`, `query`): delegate directly.

### ServiceProvider
- `register()`: singleton bindings for FieldEncryptor (with Addon DI) and SensitiveFieldResolver.
- `bootAddon()`: permission, field config (Text + Textarea only), repository decorator.
- Listener, settings, translations: all auto-discovered.

### Addon Settings (`resources/blueprints/settings.yaml`)
- `enabled` (toggle, default true)
- `mask` (text, default `••••••`)

---

## FREE vs PRO

### FREE
- Encryption at rest: sensitive fields encrypted before storage.
- All authorized CP users see decrypted values (no access control).
- No masking.

### PRO (`pro` setting toggle)
- Permission-based access control: only super admins and users with `view decrypted sensitive fields` permission see decrypted values.
- Unauthorized users see mask string (default `••••••`, configurable).
- Permission is registered only when PRO mode is enabled.

---

## PRO Plan

### Recursive re-save commands (existing submissions)

Planned as PRO-only operational commands for bulk migration of historical submissions.

1. `sensitive-fields:encrypt-existing`
   - Recursively iterates all forms and all existing submissions.
   - Resolves sensitive handles from each form blueprint.
   - Encrypts only unmarked plaintext values (`enc:v1:` guard prevents double encryption).
   - Persists updated submissions back to storage (Stache/Eloquent) via normal save flow.

2. `sensitive-fields:decrypt-existing`
   - Recursively iterates all forms and all existing submissions.
   - Resolves sensitive handles from each form blueprint.
   - Decrypts values marked with `enc:v1:`.
   - Persists updated submissions as plaintext.

### Command behavior requirements

- Works for both Stache and Eloquent driver.
- Idempotent runs (safe to execute repeatedly).
- Graceful per-submission error handling (log warning, continue processing).
- Summary output: processed forms/submissions, updated values, skipped values, errors.
- Optional filters (for implementation phase): by form handle, dry-run mode, chunk size.

---

## Tests

### Unit (FieldEncryptorTest)
1. Encrypts value with marker prefix
2. Decrypts back to plaintext
3. No double encryption
4. Failed decrypt returns raw + logs warning
5. isEncrypted detects prefix
6. mask returns configured value
7. decrypt returns non-encrypted as-is

### Feature (SensitiveFieldsTest)
1. Sensitive field stored encrypted
2. Non-sensitive field remains plain
3. Authorized user reads plaintext
4. Unauthorized user reads masked value
5. User with permission reads plaintext
6. Already-encrypted value not double-encrypted

---

## Known Limitations

1. **Search/filtering** — encrypted fields cannot be searched (ciphertext is opaque)
2. **APP_KEY rotation** — makes existing data unreadable without migration; PRO plan includes recursive encrypt/decrypt re-save commands for bulk remediation
3. **Complex field types** — only string values encrypted; arrays/grids/replicator skipped
4. **Export** — decrypted or masked based on user permission
5. **API access** — encrypted/masked unless user has permission
