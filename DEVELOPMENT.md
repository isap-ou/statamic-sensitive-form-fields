Build a free Statamic 6 addon named “Sensitive Form Fields” that encrypts sensitive form submission fields “like an Eloquent encrypted cast” (encrypt-on-write, decrypt-on-read).

Why / business goal:
EU sites often store form submissions containing sensitive personal data (email, phone, free-text messages) as plain text at rest (files/DB/backups). This increases security/GDPR exposure and the impact of a breach. The addon reduces exposure by storing selected fields encrypted at rest while keeping the admin UI and templates working with plaintext (cast-like behavior).

Core behavior:
- Encrypt-on-write: before persisting a form submission, encrypt only fields marked as Sensitive.
- Decrypt-on-read: whenever form submissions are read (CP views, exports, queries), automatically decrypt those sensitive fields back to plaintext for authorized users, without modifying stored ciphertext.

UI / configuration:
- Add a per-field toggle in Statamic FORM blueprints: “Sensitive (encrypted at rest)” (default false).
- Add this toggle to existing field settings via Fieldtype::appendConfigField / appendConfigFields.
- Prefer applying it to form-usable text-like fieldtypes (text, textarea, email) to avoid complex nested data types.

Storage & drivers:
- Must work with Statamic’s form submission storage regardless of driver (flat-file Stache and Eloquent Driver).
- Do NOT rely on dedicated DB columns; treat submission data as a key/value payload.

Implementation approach (recommended):
1) Encrypt-on-write:
    - Listen to Statamic\Events\FormSubmitted (or SubmissionCreating) and mutate $submission->data() before it is persisted.
2) Decrypt-on-read (cast-like):
    - Implement a repository decorator or equivalent interception around the Form Submission repository so that:
        - find/query results return submissions with decrypted values for sensitive fields
        - save persists encrypted values for sensitive fields
    - Decryption should be runtime-only (do not rewrite storage files/DB on read).

Encryption details:
- Use Laravel Crypt with APP_KEY: Crypt::encryptString / Crypt::decryptString.
- Store ciphertext with a detectable marker to prevent double-encryption, e.g. "enc:v1:" prefix.
- If a value is already encrypted (has prefix), do not encrypt again.
- If decrypt fails (key rotation/corrupt data), fail gracefully: return the raw ciphertext and log a warning (no fatal errors).

Security / access control:
- Add a permission like “view decrypted sensitive fields”.
- If user lacks permission, keep ciphertext in CP/export output (or optionally mask).
- Do not collect extra metadata (no IP, no user agent, no tracking).

Deliverables:
- Complete package file tree (composer.json, service provider, config, listeners, repository decorator, permissions).
- Code that:
    - reads the form blueprint, identifies handles with sensitive toggle enabled
    - encrypts those values on submit before persistence
    - decrypts those values on read for authorized users
- Minimal tests:
    - sensitive fields are stored encrypted (prefix present in storage payload)
    - reading a submission returns plaintext for authorized user
    - reading returns ciphertext/masked for unauthorized user
    - non-sensitive fields remain plain
    - already-encrypted values are not double-encrypted
- Marketplace-focused README.md:
    - business value, feature list, install/usage, limitations (search/filtering, APP_KEY rotation), and permission behavior
    - avoid “GDPR compliant” claims