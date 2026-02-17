# Security

## Encryption Method

This addon uses Laravel's `Crypt::encryptString()` / `Crypt::decryptString()`, which provides **AES-256-CBC** encryption with HMAC authentication. All encryption operations use the application's `APP_KEY`.

Encrypted values are stored with a `enc:v1:` prefix marker. This marker prevents double-encryption and allows the addon to distinguish encrypted values from plaintext.

## Key Management

- **APP_KEY dependency** — all encryption and decryption relies on the Laravel `APP_KEY` configured in `.env`. If `APP_KEY` is lost, encrypted data cannot be recovered.
- **Key rotation** — changing `APP_KEY` makes all previously encrypted submission fields unreadable. The addon handles this gracefully (returns raw ciphertext and logs a warning) but does not include a migration tool. Back up your `APP_KEY` securely and rotate it only with a plan for re-encrypting existing data.
- **Key storage** — never commit `APP_KEY` to version control. Use environment variables or a secrets manager.

## Permission Model

The addon registers a Statamic permission: **"View Decrypted Sensitive Fields"**.

| User type | Behavior |
|---|---|
| Super admin | Always sees decrypted plaintext |
| User with permission | Sees decrypted plaintext |
| User without permission | Sees masked value (default: `••••••`) |

The mask string is configurable in addon settings (CP > Tools > Addons > Sensitive Form Fields > Settings).

## Threat Model

### What this addon protects against

- **Data-at-rest exposure** — if an attacker gains read access to flat files, database dumps, or backups, sensitive field values are encrypted and not directly readable.
- **Unauthorized CP access** — users without the required permission cannot see plaintext values in the Control Panel.

### What this addon does NOT protect against

- **Compromised APP_KEY** — if an attacker obtains `APP_KEY`, they can decrypt all sensitive field values.
- **Server-level compromise** — if an attacker has full server access (including `.env`), encryption provides no additional protection.
- **In-transit security** — this addon does not handle HTTPS/TLS. Ensure your site uses HTTPS.
- **Form submission interception** — values are encrypted after form submission processing. The initial HTTP request payload is not encrypted by this addon.
- **Search and filtering** — encrypted fields cannot be searched or filtered. This is an intentional trade-off for security.

## Reporting Vulnerabilities

If you discover a security vulnerability, please report it privately by emailing the maintainers. Do not open a public issue.
