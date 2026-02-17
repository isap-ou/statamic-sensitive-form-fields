# Statamic Sensitive Form Fields

Encrypt selected form submission fields at rest and decrypt them on read for authorized users. Reduces exposure of sensitive personal data (emails, phone numbers, messages) stored in form submissions.

## Features

- **Encrypt on write** — sensitive field values are encrypted before persistence using Laravel's `Crypt` (AES-256-CBC with `APP_KEY`)
- **Decrypt on read** — authorized users see plaintext in the Control Panel; unauthorized users see a masked value (`••••••`)
- **Per-field toggle** — mark any text-like field as "Sensitive" directly in the form blueprint editor
- **No storage rewrite** — decryption is runtime-only; stored data always stays encrypted
- **Driver agnostic** — works with both Stache (flat-file) and Eloquent Driver

## How to Install

```bash
composer require isapp/statamic-sensitive-form-fields
```

## How to Use

### 1. Mark fields as sensitive

In your form blueprint, toggle **"Sensitive (encrypted at rest)"** on any text, textarea, or email field.

### 2. Assign permission

The addon registers the permission **"View Decrypted Sensitive Fields"**. Grant it to roles that should see plaintext values.

Super admins always see decrypted values. Users without the permission see `••••••` instead.

### 3. Configure addon settings

Navigate to **CP > Tools > Addons > Sensitive Form Fields > Settings** to configure:

- **Enabled** — toggle encryption on/off globally
- **Mask String** — the string shown to unauthorized users (default: `••••••`)

## How It Works

1. When a form is submitted, a `SubmissionSaving` event listener encrypts all sensitive field values with a `enc:v1:` marker prefix before persistence.
2. When submissions are read via the repository (`find`, `whereForm`, `all`, etc.), a decorator checks the current user's permission:
   - **Authorized**: strips the marker, decrypts the value
   - **Unauthorized**: replaces the value with the mask string
3. Already-encrypted values (detected by prefix) are never double-encrypted.
4. If decryption fails (e.g. after key rotation), the raw ciphertext is returned and a warning is logged.

## Limitations

- **Search and filtering** — encrypted fields cannot be searched or filtered (ciphertext is opaque)
- **APP_KEY rotation** — changing `APP_KEY` makes existing encrypted data unreadable; a migration tool is not included
- **Complex field types** — only string-based values are encrypted; arrays, grids, and replicator fields are skipped
- **Export** — CSV/JSON exports contain decrypted or masked values based on the exporting user's permission
- **API access** — REST/GraphQL responses contain encrypted or masked values unless the authenticated user has the required permission
- **Query builder** — submissions retrieved via `query()` builder are not automatically decrypted; use `find()`, `whereForm()`, or `all()`

## Testing

```bash
vendor/bin/phpunit
```
