<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Tests\Feature;

use Isapp\SensitiveFormFields\ServiceProvider;
use Isapp\SensitiveFormFields\Tests\TestCase;
use Statamic\Facades\Addon;
use Statamic\Fieldtypes\Text;
use Statamic\Fieldtypes\Textarea;
use Statamic\Statamic;

class FieldToggleScopeTest extends TestCase
{
    private const ADDON = 'isapp/statamic-sensitive-form-fields';

    private const CONDITION = 'custom sensitiveFieldSupported';

    public function test_text_sensitive_toggle_carries_the_form_scope_condition()
    {
        $field = (new Text)->configFields()->get('sensitive');

        $this->assertNotNull($field, 'The sensitive config field is not registered on the Text fieldtype.');
        $this->assertSame(self::CONDITION, $field->get('if'));
    }

    public function test_textarea_sensitive_toggle_carries_the_form_scope_condition()
    {
        $field = (new Textarea)->configFields()->get('sensitive');

        $this->assertNotNull($field, 'The sensitive config field is not registered on the Textarea fieldtype.');
        $this->assertSame(self::CONDITION, $field->get('if'));
    }

    // The condition name is written in PHP and implemented in JavaScript. Nothing else
    // couples the two: if either side is renamed alone, Statamic cannot resolve the
    // condition, returns false, and the toggle silently disappears from every editor —
    // including the form blueprint editor, where it is supposed to appear.
    public function test_cp_script_implements_the_condition_the_config_field_references()
    {
        $configured = (new Text)->configFields()->get('sensitive')->get('if');
        $name = str_replace('custom ', '', $configured);

        $this->assertStringContainsString(
            "\$conditions.add('{$name}'",
            file_get_contents($this->scriptPath()),
            "resources/js/cp.js must register the [{$name}] condition referenced by the sensitive field config."
        );
    }

    // Both paths are internal Statamic Control Panel routes. In the fieldset editor the
    // path is the only signal there is, so a route change upstream would silently hide
    // the toggle there. Pinning them means such a change breaks a test, not a customer.
    public function test_cp_script_matches_the_current_control_panel_routes()
    {
        $script = file_get_contents($this->scriptPath());

        $this->assertStringContainsString('/fields/blueprints/forms/', $script);
        $this->assertStringContainsString('/fields/fieldsets/', $script);

        $this->assertStringContainsString('/fields/blueprints/forms/', route('statamic.cp.blueprints.forms.edit', ['form' => 'test'], false));
        $this->assertStringContainsString('/fields/fieldsets/', route('statamic.cp.fieldsets.edit', ['fieldset' => 'test'], false));
    }

    public function test_control_panel_script_is_registered_and_present_on_disk()
    {
        $package = Addon::get(self::ADDON)->packageName();
        $scripts = Statamic::availableScripts(request());

        $this->assertArrayHasKey($package, $scripts);
        $this->assertStringStartsWith('cp.js', $scripts[$package][0]);
        $this->assertFileExists($this->scriptPath());
    }

    // Resolved from the provider's own $scripts declaration rather than hardcoded, so a
    // path that points at a file which is not there fails here instead of in a browser.
    private function scriptPath(): string
    {
        $scripts = (new \ReflectionClass(ServiceProvider::class))
            ->getDefaultProperties()['scripts'];

        $this->assertCount(1, $scripts, 'Expected exactly one declared Control Panel script.');

        return $scripts[0];
    }
}
