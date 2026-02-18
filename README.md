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
- If you ever need to rotate `APP_KEY`, use `sensitive-fields:rekey` (Pro) to re-encrypt submissions under the new key before traffic hits the rotated key (see [Re-key after APP_KEY rotation](#3-pro-re-key-after-appkey-rotation))

> A lost or rotated `APP_KEY` = unrecoverable submission data. The addon logs a warning and returns raw ciphertext on decryption failure, but cannot recover data without the original key.

---

## Usage

### 1. Mark fields as sensitive

Open any form blueprint in the Control Panel. On text or textarea fields, enable **"Sensitive (encrypted at rest)"**.

From this point on, new submissions will have those field values encrypted before storage.

### 2. [Pro] Assign the permission

Go to **CP → Users → Roles** and grant a permission to roles that should see plain text. Super admins always see decrypted values regardless of role.

Two permission levels are available:

- **View Decrypted Sensitive Fields** (global) — grants access to decrypted values across **all** forms. Use this for administrator roles.
- **View Decrypted Sensitive Fields** per-form — grants access to decrypted values in **one specific form** only. Each form gets its own entry in the Roles editor. Use this to give role-specific access (e.g. HR reads the job-application form but not the contact form).

Users without a matching permission see `••••••` instead of the actual value.

### 3. [Pro] Re-key after APP_KEY rotation

The command re-encrypts existing submissions using the **current** `APP_KEY`, so the new key must already be in place before you run it:

1. Back up the old `APP_KEY` value.
2. Set the **new** `APP_KEY` in your `.env` (and clear config cache if necessary).
3. Run the rekey command. When invoked without `--old-key`, it will prompt for the key interactively (input is hidden):

```bash
php artisan sensitive-fields:rekey
```

For non-interactive environments (CI/CD), pass the key via the option — but be aware it will appear in shell history and process listings:

```bash
php artisan sensitive-fields:rekey --old-key="base64:YOUR_OLD_APP_KEY"
```

Options:

- `--old-key` — the previous `APP_KEY` value (optional; prompted if omitted)
- `--form=<handle>` — limit to a single form
- `--dry-run` — preview without writing

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
4. If decryption fails (e.g. after `APP_KEY` rotation), the raw ciphertext is returned, a warning is logged, and an error toast is shown in the CP (once per form per hour to avoid notification spam).

---

## Limitations

- **Search and filtering** — encrypted values are opaque; filtering or searching on sensitive fields will not work
- **APP_KEY rotation** — changing `APP_KEY` breaks existing encrypted data; set the new key first, then use `sensitive-fields:rekey --old-key=<previous-key>` (Pro) to re-encrypt (see [Re-key after APP_KEY rotation](#3-pro-re-key-after-appkey-rotation))
- **Complex field types** — only string-based fields are encrypted; arrays, grids, and replicator fields are skipped
- **Export** — CSV and JSON exports contain decrypted or masked values based on the exporting user's permission (Pro)
- **API** — REST and GraphQL responses respect the same permission rules (Pro)

---

## Changelog

Release notes are published via [GitHub Releases](https://github.com/isapp/statamic-sensitive-form-fields/releases).

Version tags follow Semantic Versioning without a `v` prefix (e.g. `1.0.0`).
