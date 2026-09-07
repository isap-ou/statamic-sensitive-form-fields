# Spec: Scope the "Sensitive" field toggle to form blueprints

Status: Approved

## Context & Goal

A user reported that the **"Sensitive (encrypted at rest)"** toggle appears on `text` and
`textarea` fields in *every* blueprint — collections, taxonomies, users, globals — even though
the addon only encrypts **form submissions**.

The cause is structural: `Text::appendConfigField()` / `Textarea::appendConfigField()`
(`src/ServiceProvider.php`) write into a static, per-fieldtype-class registry
(`Statamic\Fields\Fieldtype::$extraConfigFields`) that is read unconditionally and cached by
fieldtype handle. Neither `Fieldtype::configFields()` nor the `POST /cp/fields/edit` endpoint
that feeds the CP field-settings panel receives the blueprint being edited, so no supported
server-side seam exists for scoping the toggle.

(Statamic itself sniffs the HTTP `Referer` header in that same controller to detect a forms
blueprint. That is a real, if unsupported, server-side option — rejected here because the
header is absent under stricter referrer policies, which would hide the toggle for those
users with no second signal to fall back on.)

Goal: offer the toggle only where it has an effect, so the UI stops promising more than the
addon delivers. Encryption behaviour itself does not change.

## User stories

- As a site builder editing a collection, taxonomy, user or globals blueprint, I do not see a
  "Sensitive" toggle that would do nothing.
- As a site builder editing a form blueprint — or a fieldset that can be imported into a form —
  I still see and can use the toggle, and the instructions tell me where it applies.

## Functional requirements

- **FR-1**: In the CP field-settings panel, the `sensitive` config toggle is hidden unless the
  field is edited in a context where sensitive encryption can apply.
- **FR-2**: An applicable context is the Forms blueprint editor
  (`{cp}/fields/blueprints/forms/{form}/edit`) or the Fieldset editor
  (`{cp}/fields/fieldsets/{handle}/edit`).
- **FR-3**: Context detection uses two independent signals — the `isFormBlueprint` CP config
  flag, or a match on the editor URL path. Detection fails closed: an unknown context hides the
  toggle.
- **FR-4**: The condition is registered through the supported `Statamic.$conditions.add()` API
  and attached to the config field as `if: 'custom <name>'`.
- **FR-5**: The CP script ships via `AddonServiceProvider::$scripts` as plain JavaScript, with
  no build step.
- **FR-6**: Stored `sensitive: true` values are never modified. Hiding is display-only;
  encryption and decryption behaviour is unchanged.
- **FR-7**: The field toggle instructions state that the setting only applies within form
  blueprints.

## Acceptance criteria

- **AC-1**: Test — the `sensitive` config field on the `Text` fieldtype carries the custom
  condition.
- **AC-2**: Test — the same holds for the `Textarea` fieldtype.
- **AC-3**: Test — the script path declared in `$scripts` exists on disk, resolved from the
  provider's own declaration rather than a hardcoded path.
- **AC-3a**: Test — the CP script implements the condition name the config field references.
  Nothing else couples the PHP string to the JavaScript one; a rename on either side alone
  makes Statamic return `false` and hides the toggle in every editor.
- **AC-3b**: Test — the two route paths hardcoded in the CP script match Statamic's own route
  definitions, so an upstream route change fails a test instead of silently hiding the toggle.
- **AC-4**: Manual CP verification — the toggle is visible in the form blueprint editor and the
  fieldset editor; hidden in collection, taxonomy, user and globals blueprint editors, including
  after a hard page reload.
- **AC-5**: `vendor/bin/phpunit` passes with no regressions.
- **AC-6**: Documentation updated per the `AGENTS.md` table — `lang/en/messages.php`,
  `README.md`, `docs/OVERVIEW.md`, `docs/PLAN.md`, `CHANGELOG.md`.

## Non-goals

- Extending encryption to entries, terms, users or globals. That was the reporter's other
  question and remains a separate decision.
- Server-side stripping of the `sensitive` key from non-form blueprints on save. This task is
  UI-only.
- A dedicated `sensitive_text` fieldtype — it would break existing blueprints.
- A Vite build pipeline or JavaScript test infrastructure.

## Edge cases

- On a hard load of a non-form blueprint editor the `isFormBlueprint` flag is `undefined` rather
  than `false`, because the blueprint builder only sets it for form blueprints. The condition
  must therefore fail closed.
- The fieldset editor renders the field builder without the blueprint-builder wrapper, so the
  flag is never set there; that context is recognised by URL only.
- Nested fields inside Grid, Replicator or Bard sets in a form blueprint use the same settings
  panel on the same page, so the URL signal covers them.
- The `isFormBlueprint` flag and both CP routes are internal Statamic surface, not documented
  API. Should a future release rename either, the toggle could silently disappear from the form
  editor. Two independent signals reduce the risk; the coupling is documented in
  `docs/OVERVIEW.md`.
- The CP script is a published asset. `php artisan statamic:install` publishes it and a
  standard Statamic site runs that from Composer's `post-autoload-dump`, so ordinary upgrades
  need no manual step; a deployment that skips Composer scripts or ships a prebuilt `public/`
  must run `php artisan vendor:publish --tag=<addon-slug> --force`, or the toggle is hidden
  everywhere.
- A `sensitive: true` key left on a non-form blueprint by an earlier version is preserved but
  becomes invisible in the Control Panel; removing it requires editing the blueprint YAML.
  Accepted — stripping it would violate the server-side Non-goal below.

## Open questions

None.
