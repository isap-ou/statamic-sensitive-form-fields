# Changelog

All notable changes to this project are documented in this file.

This changelog is used as the base for GitHub Release notes.

## Unreleased

## 1.0.0 (2026-02-17)

- [new] Per-field `sensitive` toggle on Text and Textarea fieldtypes in the form blueprint editor
- [new] Sensitive field values are encrypted at rest using Laravel's `Crypt` with an `enc:v1:` prefix — no double encryption
- [new] Fields are encrypted automatically on form submission via a `SubmissionSaving` listener
- [new] Decryption on read via a `DecryptingSubmissionRepository` decorator — covers `find()`, `whereForm()`, `all()`
- [new] Decryption on read via a `DecryptingSubmissionQueryBuilder` decorator — covers the CP submissions list (which uses `query()`)
- [new] Compatible with both Stache (flat-file) and Eloquent driver
- [new] Addon setting to enable or disable encryption globally
- [new] Addon setting to configure the mask string shown to unauthorized users
- [new] PRO mode: permission-based access control and masking; activated automatically when the PRO addon is installed
- [new] FREE mode: all CP users see decrypted values; data always encrypted at rest
- [new] Translations (en) for field toggle, permission label, and settings
