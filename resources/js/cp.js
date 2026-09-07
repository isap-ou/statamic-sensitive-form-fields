/**
 * Scopes the "Sensitive (encrypted at rest)" field config toggle to the places where the
 * addon can actually encrypt: form blueprints, and fieldsets that may be imported into one.
 *
 * Statamic appends fieldtype config fields through a static, per-fieldtype-class registry
 * with no blueprint context (Statamic\Fields\Fieldtype::appendConfigField), so the scoping
 * has to happen in the Control Panel. The condition is registered here and referenced from
 * the field config as `if: 'custom sensitiveFieldSupported'`.
 *
 * Two independent signals are used, and detection fails closed:
 *
 * 1. `isFormBlueprint` — set by the blueprint builder only while the Forms blueprint editor
 *    is mounted. It is `undefined` rather than `false` on a hard load of any other blueprint
 *    editor, so it cannot be trusted on its own.
 * 2. The editor URL — the only signal available in the fieldset editor, which renders the
 *    field builder without the blueprint builder wrapper.
 *
 * Both are internal Control Panel surface rather than documented API. In the form blueprint
 * editor either signal alone suffices, so an upstream rename would have to hit both before
 * the toggle disappears there. The fieldset editor has no such redundancy — the path is its
 * only signal. Both paths are pinned against Statamic's own routes in FieldToggleScopeTest.
 */
Statamic.booting(() => {
    Statamic.$conditions.add('sensitiveFieldSupported', () => {
        if (Statamic.$config.get('isFormBlueprint') === true) {
            return true;
        }

        const path = window.location.pathname;

        return path.includes('/fields/blueprints/forms/') || path.includes('/fields/fieldsets/');
    });
});
