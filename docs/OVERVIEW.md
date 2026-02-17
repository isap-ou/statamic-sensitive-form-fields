# Project Overview

## Requirements

- PHP 8.2+
- Statamic 6
- Laravel 12

## Architecture

### File Tree

```
src/
├── Encryption/
│   └── FieldEncryptor.php          # Encrypt/decrypt logic with enc:v1: marker
├── Listeners/
│   └── EncryptSensitiveFields.php  # SubmissionSaving event listener
├── Repositories/
│   └── DecryptingSubmissionRepository.php  # Decorator for read-path decryption
├── Support/
│   └── SensitiveFieldResolver.php  # Resolves sensitive handles from blueprint
└── ServiceProvider.php             # Wires everything together

resources/
└── blueprints/
    └── settings.yaml               # Addon settings blueprint (enabled, mask)

tests/
├── Unit/
│   └── FieldEncryptorTest.php      # 7 unit tests for encryption logic
└── Feature/
    └── SensitiveFieldsTest.php     # 6 feature tests for full flow
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

1. Code requests a submission via the repository (`find`, `whereForm`, `all`).
2. `DecryptingSubmissionRepository` decorator intercepts the result.
3. For each submission, it:
   - Resolves sensitive field handles from the form's blueprint.
   - Checks the current user's permission (`view decrypted sensitive fields`).
   - **Authorized**: strips `enc:v1:` prefix and decrypts the value.
   - **Unauthorized**: replaces the value with the mask string (default `••••••`).
4. If decryption fails (e.g. key rotation), returns raw ciphertext and logs a warning.

### Addon Settings

Settings are managed via Statamic's addon settings UI (CP > Tools > Addons > Sensitive Form Fields > Settings), defined in `resources/blueprints/settings.yaml`:

- **Enabled** — global toggle for encryption (default: `true`)
- **Mask String** — value shown to unauthorized users (default: `••••••`)

No config file is published. Settings are stored by Statamic's addon settings system.

### Permission

The addon registers a custom permission: **"View Decrypted Sensitive Fields"** under the Forms permission group. Super admins always have this permission implicitly.

### Field Configuration

A "Sensitive (encrypted at rest)" toggle is appended to Text and Textarea fieldtype config panels via `Text::appendConfigField()` and `Textarea::appendConfigField()`. Form builders enable it per-field in the blueprint editor.

## Testing

Run the full test suite:

```bash
vendor/bin/phpunit
```

### Test Coverage

- **Unit tests** (`FieldEncryptorTest`): marker detection, encrypt/decrypt round-trip, double-encryption prevention, non-string skipping, decrypt failure handling, custom marker support.
- **Feature tests** (`SensitiveFieldsTest`): full write/read flow with authorization, masked output for unauthorized users, non-sensitive field passthrough, disabled addon bypass.

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Write tests for your changes.
4. Run `vendor/bin/phpunit` and ensure all tests pass.
5. Submit a pull request.
