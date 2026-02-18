# Sensitive Form Fields

Encrypt selected form submission fields before they are written to disk or database. Personal data — emails, phone numbers, messages — stays encrypted at rest and is decrypted at runtime only for authorized users.

---

## Free vs Pro

| Feature | Free | Pro |
|---------|:----:|:---:|
| AES-256-CBC encryption at rest | ✓ | ✓ |
| Per-field "Sensitive" toggle in blueprint editor | ✓ | ✓ |
| Works with Stache and Eloquent Driver | ✓ | ✓ |
| Double-encryption guard | ✓ | ✓ |
| Global enable/disable toggle | ✓ | ✓ |
| All CP users see decrypted values | ✓ | — |
| Role-based access control | — | ✓ |
| Masked values for unauthorized users | — | ✓ |
| Configurable mask string (default: `••••••`) | — | ✓ |
| Re-key on APP_KEY rotation | — | ✓ |

---

## Requirements

- PHP 8.2+
- Statamic 6+

## Installation

```bash
composer require isapp/statamic-sensitive-form-fields
```

---

## Before You Start: APP_KEY

This addon encrypts data using Laravel's `Crypt`, which relies entirely on your application's `APP_KEY`.

**If `APP_KEY` changes, all previously encrypted submission data becomes permanently unreadable.**

There is no recovery path without the original key. Before enabling this addon on a production site:

- Confirm your `APP_KEY` is backed up securely (password manager, secrets vault)
- Never commit `.env` to version control
- If you ever need to rotate `APP_KEY`, decrypt and re-encrypt all sensitive submissions first

> A lost or rotated `APP_KEY` = unrecoverable submission data. The addon logs a warning and returns raw ciphertext on decryption failure, but cannot recover data without the original key.

---

## Usage

### 1. Mark fields as sensitive

Open any form blueprint in the Control Panel. On text or textarea fields, enable **"Sensitive (encrypted at rest)"**.

From this point on, new submissions will have those field values encrypted before storage.

### 2. [Pro] Assign the permission

Go to **CP → Users → Roles** and grant **"View Decrypted Sensitive Fields"** to roles that should see plain text. Super admins always see decrypted values regardless of role.

Users without the permission see `••••••` instead of the actual value.

### 3. [Pro] Re-key after APP_KEY rotation

If you need to rotate `APP_KEY`, first re-encrypt all existing sensitive submissions using the old key:

```bash
php artisan sensitive-fields:rekey --old-key="base64:YOUR_OLD_APP_KEY"
```

Options:

- `--old-key` — the previous `APP_KEY` value from your `.env` (required)
- `--form=<handle>` — limit to a single form
- `--dry-run` — preview without writing

After the command completes successfully, update `APP_KEY` in your `.env`.

> If the command reports errors for some submissions, those values could not be decrypted with the provided key and are left unchanged.

### 4. [Pro] Configure addon settings

Go to **CP → Tools → Addons → Sensitive Form Fields → Settings**:

- **Enabled** — toggle encryption on/off globally
- **Mask String** — text shown to users without the permission (default: `••••••`)

---

## How It Works

1. On form submission, a `SubmissionSaving` listener encrypts sensitive field values before they are written to storage. Encrypted values are prefixed with `enc:v1:`.
2. On read, a repository decorator processes each sensitive value:
   - **Free tier** — decrypts and returns plain text for all CP users
   - **Pro, authorized** — decrypts and returns plain text
   - **Pro, unauthorized** — returns the configured mask string
3. Values already prefixed with `enc:v1:` are never double-encrypted.
4. If decryption fails (e.g. after `APP_KEY` rotation), the raw ciphertext is returned and a warning is logged.

---

## Limitations

- **Search and filtering** — encrypted values are opaque; filtering or searching on sensitive fields will not work
- **APP_KEY rotation** — changing `APP_KEY` breaks existing encrypted data; use `sensitive-fields:rekey` (Pro) to re-encrypt before rotating the key (see [Before You Start](#before-you-start-appkey))
- **Complex field types** — only string-based fields are encrypted; arrays, grids, and replicator fields are skipped
- **Export** — CSV and JSON exports contain decrypted or masked values based on the exporting user's permission (Pro)
- **API** — REST and GraphQL responses respect the same permission rules (Pro)

---

## Changelog

Release notes are published via [GitHub Releases](https://github.com/isapp/statamic-sensitive-form-fields/releases).

Version tags follow Semantic Versioning without a `v` prefix (e.g. `1.0.0`).
