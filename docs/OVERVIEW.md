# Project Overview

## Requirements

- PHP 8.2+
- Statamic 6
- Laravel 12

## Architecture

### File Tree

```
src/
├── Commands/
│   ├── EncryptExistingCommand.php           # [PRO] Bulk-encrypt existing submissions
│   ├── DecryptExistingCommand.php           # [PRO] Bulk-decrypt existing submissions
│   └── RekeyCommand.php                     # [PRO] Re-encrypt from an old APP_KEY to the current one
├── Encryption/
│   └── FieldEncryptor.php                   # Encrypt/decrypt logic with enc:v1: marker
├── Listeners/
│   └── EncryptSensitiveFields.php           # SubmissionSaving event listener
├── Repositories/
│   ├── DecryptingSubmissionRepository.php   # Decorator for read-path decryption (find/whereForm/all/query)
│   ├── DecryptingSubmissionQueryBuilder.php # Decorator wrapping query() results
│   └── RawSubmissionRepository.php          # Marker interface for undecorated repository (used by PRO commands)
├── Support/
│   └── SensitiveFieldResolver.php           # Resolves sensitive handles from blueprint
└── ServiceProvider.php                      # Wires everything together

resources/
└── js/
    └── cp.js                       # CP script — registers the sensitiveFieldSupported field condition

lang/
└── en/
    └── messages.php                # Translations (auto-discovered)

tests/
├── TestCase.php
├── Unit/
│   └── FieldEncryptorTest.php      # 7 unit tests for encryption logic
└── Feature/
    ├── SensitiveFieldsTest.php     # 12 feature tests for the full flow
    ├── FieldToggleScopeTest.php    # 5 feature tests for CP toggle scoping
    ├── ProCommandsTest.php         # 6 feature tests for PRO commands
    └── RekeyCommandTest.php        # 7 feature tests for the rekey command
```

### Write Path (Encryption)

1. User submits a form.
2. Statamic dispatches `SubmissionSaving` event before persistence.
3. `EncryptSensitiveFields` listener:
   - Checks if addon is enabled (via addon settings).
   - Resolves sensitive field handles from the form's blueprint.
   - Encrypts string values using `FieldEncryptor`, prepending `enc:v1:` marker.
   - Skips already-encrypted values (prevents double-encryption).
   - Mutates `$submission->data()` in place before persistence.

### Read Path (Decryption)

1. Code requests a submission via the repository (`find`, `whereForm`, `all`, or `query()`).
2. `DecryptingSubmissionRepository` decorator intercepts the result; `query()` is wrapped by `DecryptingSubmissionQueryBuilder`.
3. For each submission, it:
   - Resolves sensitive field handles from the form's blueprint.
   - Checks the current user's permission (`view decrypted sensitive fields`).
   - **Authorized**: strips `enc:v1:` prefix and decrypts the value.
   - **Unauthorized**: replaces the value with the mask string (default `••••••`).
4. If decryption fails (e.g. key rotation), returns raw ciphertext and logs a warning.

### Addon Settings

Settings are managed via Statamic's addon settings UI (CP > Tools > Addons > Sensitive Form Fields > Settings). The blueprint is registered in code by `ServiceProvider::registerSettings()` via `registerSettingsBlueprint()`:

- **Enabled** — global toggle for encryption (default: `true`)
- **Mask String** — PRO only; value shown to unauthorized users (default: `••••••`)

No config file is published. Settings are stored by Statamic's addon settings system.

### Editions

The addon supports Statamic Editions (`"editions": ["free", "pro"]` in `composer.json`). Edition is detected at runtime via `Addon::edition()`, which reads `config('statamic.editions.addons.isapp/statamic-sensitive-form-fields')`. When unconfigured, the first edition (`free`) is used. PRO is activated by the Statamic Marketplace license system or by setting the config value manually.

### Permission

The addon registers two custom permissions under the Forms permission group:

- **`view decrypted sensitive fields`** — global wildcard; grants decrypted access to all forms.
- **`view decrypted {form-handle} sensitive fields`** — per-form; one entry is generated per registered form using Statamic's native `{placeholder}` + `replacements()` mechanism (same pattern as `view {collection} entries`).

Super admins always have decrypted access implicitly, regardless of role assignments.

### Field Configuration

A "Sensitive (encrypted at rest)" toggle is appended to Text and Textarea fieldtype config panels via `Text::appendConfigField()` and `Textarea::appendConfigField()`. Form builders enable it per-field in the blueprint editor.

`appendConfigField()` writes into a static, per-fieldtype-class registry that carries no blueprint context, and the `POST /cp/fields/edit` endpoint serving the field-settings panel is never told which blueprint is being edited. Left alone, the toggle would therefore render in *every* blueprint, including collections, taxonomies, users and globals — where the addon encrypts nothing.

It is scoped in the Control Panel instead:

- The config field carries `if => 'custom sensitiveFieldSupported'`. Conditions are a pass-through config key, so it reaches the CP untouched.
- `resources/js/cp.js` registers that condition through `Statamic.$conditions.add()` and is shipped via `ServiceProvider::$scripts`.
- The condition returns true when the CP config flag `isFormBlueprint` is `true`, or when the current path is the form blueprint editor (`/fields/blueprints/forms/`) or the fieldset editor (`/fields/fieldsets/`).

**Known coupling.** The `isFormBlueprint` flag and both route paths are internal Control Panel surface, not documented API. If the toggle ever goes missing after a Statamic upgrade, this is the first place to look.

- In the **form blueprint editor** either signal alone suffices, so an upstream rename would have to hit both.
- In the **fieldset editor** there is no redundancy: the flag is never set there, so the path is the only signal.
- `FieldToggleScopeTest` pins both paths against Statamic's own route definitions, so an upstream route change fails a test rather than silently hiding the toggle.

Detection deliberately fails closed — the flag is `undefined` rather than `false` on a hard load of a non-form blueprint editor. Failing closed is safe only because hiding is non-destructive: the field-settings panel posts the full value set rather than the condition-filtered one, so a hidden toggle never strips a stored `sensitive: true`. If that ever changes upstream, this trade-off has to be revisited — failing closed would then mean silently un-encrypting live fields.

The script is a published asset. `php artisan statamic:install` publishes it, and a standard Statamic site runs that from Composer's `post-autoload-dump`, so ordinary installs and upgrades need no manual step. A deployment that skips Composer scripts or ships a prebuilt `public/` must run `php artisan vendor:publish --tag=statamic-sensitive-form-fields --force`; without the script the condition is unregistered, Statamic's validator returns `false`, and the toggle stays hidden in every editor — including the form blueprint editor.

## Testing

Run the full test suite:

```bash
vendor/bin/phpunit
```

### Test Coverage

37 tests in total.

- **Unit tests** (`FieldEncryptorTest`, 7 tests): marker detection, encrypt/decrypt round-trip, double-encryption prevention, decrypt failure handling, mask value, pass-through of non-encrypted values.
- **Feature tests** (`SensitiveFieldsTest`, 12 tests): full write/read flow, free/pro mode, permission-based masking, query-builder decryption, per-form permission scoping.
- **Feature tests** (`FieldToggleScopeTest`, 5 tests): the sensitive config field carries the CP scope condition on both fieldtypes; the CP script implements the condition name the config references; the script's route paths match Statamic's own routes; the script is registered and present at the declared path.
- **PRO command tests** (`ProCommandsTest`, 6 tests): bulk encrypt/decrypt, dry-run, skip-already-encrypted.
- **PRO command tests** (`RekeyCommandTest`, 7 tests): re-encryption with the current key, plaintext skipping, dry-run, missing/invalid old key, already-current values, per-submission error reporting.

### PRO Commands

Three Artisan commands for bulk migration of historical submissions are available in PRO mode. All are **idempotent** — safe to run multiple times. All support `--form=<handle>` to target a single form and `--dry-run` to preview changes without writing.

```bash
# Encrypt all plaintext sensitive fields in existing submissions
php artisan sensitive-fields:encrypt-existing

# Decrypt all encrypted sensitive fields in existing submissions
php artisan sensitive-fields:decrypt-existing

# Re-encrypt sensitive fields from a previous APP_KEY to the current one
php artisan sensitive-fields:rekey
```

The commands read from the underlying (raw) storage directly — bypassing the `DecryptingSubmissionRepository` decorator — and write back via the repository's `save()` method without firing `SubmissionSaving`, preventing re-encryption on decrypt.

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Write tests for your changes.
4. Run `vendor/bin/phpunit` and ensure all tests pass.
5. Submit a pull request.
