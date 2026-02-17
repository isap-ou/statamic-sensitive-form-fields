# AGENTS.md

## Scope
This file defines working rules for AI coding agents in this repository.

## Language Policy
- All project documentation must be written in English.
- Keep technical wording precise and implementation-focused.

## Documentation Maintenance
- At the start of work, review: `README.md`, `DEVELOPMENT.md`, `SECURITY.md`, `LICENSE.md`.
- Update these files when implementation, requirements, security behavior, or licensing details change.
- If `SECURITY.md` or `LICENSE.md` is missing, explicitly note the gap and create/update the file when task scope requires it.
- Do not store `memory.md` in this repository; Claude memory is maintained outside the project and must still be kept current.

## Project Context
- Package: `isapp/statamic-sensitive-form-fields`
- Product: Statamic 6 addon `Sensitive Form Fields`
- Main goal: encrypt selected form submission fields at rest while preserving plaintext UX for authorized readers.

## Product Requirements
- Implement encrypt-on-write for fields marked as sensitive in form blueprints.
- Implement decrypt-on-read behavior similar to Eloquent encrypted casts.
- Keep storage driver agnostic (Stache flat-file and Eloquent Driver).
- Treat form data as key/value payload; do not depend on dedicated DB columns.

## Encryption Rules
- Use Laravel `Crypt::encryptString` and `Crypt::decryptString` with `APP_KEY`.
- Store encrypted values with prefix marker: `enc:v1:`.
- Do not double-encrypt values already containing the marker.
- If decryption fails, return raw value and log a warning without throwing fatal errors.

## Access Control
- Support permission: `view decrypted sensitive fields`.
- If permission is missing, return non-decrypted output (ciphertext or a deterministic mask, based on project decision).

## Implementation Guidance
- Add a per-field blueprint toggle: `Sensitive (encrypted at rest)` (default: `false`).
- Prefer initial support for text-like fieldtypes (`text`, `textarea`, `email`).
- Intercept write path before persistence.
- Intercept read path in repository/decorator layer for runtime decryption only.

## Testing Baseline
- Sensitive fields are saved encrypted with marker present.
- Authorized reads return plaintext.
- Unauthorized reads return non-plaintext behavior.
- Non-sensitive fields stay plain.
- Already encrypted input is not encrypted again.

## Delivery Expectations
- Keep changes cohesive and minimal.
- Update docs when behavior changes.
- Include clear limitations (search/filter behavior, key rotation impact, supported fieldtypes).
