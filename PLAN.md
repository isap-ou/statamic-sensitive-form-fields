# Implementation Plan — Sensitive Form Fields

## Architecture Overview

```
ServiceProvider (bootAddon)
├── Register permission "view decrypted sensitive fields"
├── Append config toggle "sensitive" to all Fieldtype instances
├── Register SubmissionSaving listener → encrypt before write
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
  → DecryptingSubmissionRepository::find() / whereForm() / query()
    → delegates to original repository
    → checks user permission "view decrypted sensitive fields"
    → if authorized: strips "enc:v1:" prefix, decrypts via Crypt::decryptString
    → if unauthorized: returns masked value "••••••"
    → if decrypt fails: logs warning, returns raw ciphertext
```

---

## File Tree (to create)

```
src/
├── ServiceProvider.php              ← extend existing
├── Encryption/
│   └── FieldEncryptor.php           ← encrypt/decrypt + marker logic
├── Listeners/
│   └── EncryptSensitiveFields.php   ← SubmissionSaving listener
├── Repositories/
│   └── DecryptingSubmissionRepository.php  ← decorator
└── Support/
    └── SensitiveFieldResolver.php   ← reads blueprint, returns sensitive handles

config/
└── sensitive-form-fields.php        ← mask string, enabled toggle

tests/
├── TestCase.php                     ← keep existing
├── Unit/
│   └── FieldEncryptorTest.php       ← encrypt/decrypt/marker unit tests
└── Feature/
    └── SensitiveFieldsTest.php      ← integration tests (5 required scenarios)
```

---

## Step-by-step Plan

### Step 1 — FieldEncryptor service

**File:** `src/Encryption/FieldEncryptor.php`

Stateless service with methods:
- `encrypt(string $value): string` — returns `enc:v1:` + `Crypt::encryptString($value)`. Skips if already prefixed.
- `decrypt(string $value): string` — strips prefix, calls `Crypt::decryptString()`. On failure logs warning, returns raw value.
- `isEncrypted(string $value): bool` — checks `enc:v1:` prefix.
- `mask(): string` — returns configured mask string (default `••••••`).

No dependencies beyond Laravel `Crypt` facade and `Log`.

### Step 2 — SensitiveFieldResolver

**File:** `src/Support/SensitiveFieldResolver.php`

- `resolve(Form $form): array` — iterates `$form->blueprint()->fields()->all()`, returns array of handles where field config `sensitive` === `true`.
- Filters to text-like types (text, textarea, email) to avoid complex nested data.

### Step 3 — EncryptSensitiveFields listener

**File:** `src/Listeners/EncryptSensitiveFields.php`

- Listens to `Statamic\Events\SubmissionSaving`.
- Uses `SensitiveFieldResolver` to get sensitive handles for `$event->submission->form()`.
- For each sensitive handle, calls `FieldEncryptor::encrypt()` on the value via `$submission->set()`.
- Skips null/empty values.

### Step 4 — DecryptingSubmissionRepository decorator

**File:** `src/Repositories/DecryptingSubmissionRepository.php`

- Implements `Statamic\Contracts\Forms\SubmissionRepository`.
- Wraps the original repository (injected via constructor).
- Overrides read methods (`find`, `whereForm`, `whereInForm`, `all`, `query`) to post-process results:
  - Resolve sensitive handles per submission's form.
  - If current user has permission → decrypt values.
  - If no permission → replace with mask.
- Write methods (`save`, `delete`) delegate directly without modification (encryption happens in listener).
- `query()` returns a query builder; decryption will be applied by hooking into query result retrieval — wrap the query builder's `get()` result via a custom query builder decorator or process after retrieval.

**Query builder approach:** Rather than decorating the query builder (complex), the decorator will process collections returned by `whereForm`, `whereInForm`, `all`. For `find` it processes a single submission. For `query()`, we'll use a macro or post-processing approach on the builder's results.

**Simplified approach:** Instead of a full repository decorator, use Statamic's `Submission` augmentation layer. However, the decorator approach is cleaner for permission-gated decryption. We'll implement the decorator for `find`, `all`, `whereForm`, `whereInForm` and delegate `query()`, `make()`, `save()`, `delete()` as-is. For query builder results, we add a `SubmissionSaved` / `SubmissionCreated` event-based approach isn't needed — the main CP access goes through `find` and `whereForm`.

### Step 5 — Config file

**File:** `config/sensitive-form-fields.php`

```php
return [
    'enabled' => true,
    'mask' => '••••••',
];
```

### Step 6 — Permission registration

In `ServiceProvider::bootAddon()`:

```php
Permission::extend(function () {
    Permission::register('view decrypted sensitive fields')
        ->label('View Decrypted Sensitive Fields')
        ->description('Allow viewing decrypted values of sensitive form fields');
});
```

### Step 7 — Field config toggle

In `ServiceProvider::bootAddon()`:

```php
Fieldtype::appendConfigField('sensitive', [
    'type' => 'toggle',
    'display' => 'Sensitive (encrypted at rest)',
    'instructions' => 'When enabled, this field\'s value will be encrypted before storage.',
    'default' => false,
    'width' => 50,
]);
```

This appends to **all** fieldtypes globally via `Fieldtype::class` base. Alternatively, target specific types (text, textarea, email) by calling `appendConfigField` on each.

### Step 8 — ServiceProvider wiring

Update `src/ServiceProvider.php`:
- Register `FieldEncryptor` as singleton.
- Register `SensitiveFieldResolver` as singleton.
- Bind `DecryptingSubmissionRepository` as decorator around the existing `SubmissionRepository`.
- Register `EncryptSensitiveFields` listener for `SubmissionSaving`.
- Register permission.
- Append field config.
- Merge config file.

### Step 9 — Tests

**File:** `tests/Unit/FieldEncryptorTest.php`
1. Encrypts a value and result starts with `enc:v1:`.
2. Decrypts an encrypted value back to plaintext.
3. `encrypt()` skips already-encrypted value (no double encryption).
4. Failed decrypt returns raw value and logs warning.

**File:** `tests/Feature/SensitiveFieldsTest.php`
5. Sensitive field is stored encrypted (marker prefix present in persisted data).
6. Authorized user reads plaintext.
7. Unauthorized user reads masked value.
8. Non-sensitive field remains plain after save.
9. Already-encrypted value is not double-encrypted on re-save.

### Step 10 — Documentation

- Update `README.md` with: business value, features, install, usage, limitations, permission behavior.
- Create `SECURITY.md` with: encryption method, key management, permission model, threat model limitations.
- Create `LICENSE.md` with MIT license.

---

## Known Limitations to Document

1. **Search/filtering** — encrypted fields cannot be searched or filtered (ciphertext is opaque).
2. **APP_KEY rotation** — changing APP_KEY makes existing encrypted data unreadable; migration tool not included in v1.
3. **Complex field types** — only text, textarea, email supported; arrays/grids/replicator excluded.
4. **Export** — CSV/JSON exports will contain decrypted or masked values based on user permission at export time.
5. **API access** — REST/GraphQL will return encrypted/masked values unless authenticated user has permission.

---

## Implementation Order

1. `FieldEncryptor` (no dependencies, unit-testable immediately)
2. `SensitiveFieldResolver` (depends only on Statamic Form/Blueprint)
3. `EncryptSensitiveFields` listener (depends on 1 + 2)
4. `DecryptingSubmissionRepository` (depends on 1 + 2)
5. `ServiceProvider` wiring (depends on 1–4)
6. `config/sensitive-form-fields.php`
7. Tests (unit → feature)
8. Documentation (README, SECURITY, LICENSE)
