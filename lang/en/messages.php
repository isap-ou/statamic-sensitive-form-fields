<?php

declare(strict_types=1);

return [
    'field_toggle_display' => 'Sensitive (encrypted at rest)',
    'field_toggle_instructions' => 'When enabled, this field\'s value will be encrypted before storage.',

    'permission_label' => 'View Decrypted Sensitive Fields',
    'permission_description' => 'Allow viewing decrypted values of sensitive form fields',

    'permission_form_label' => 'View Decrypted Sensitive Fields',
    'permission_form_description' => 'Allow viewing decrypted values of sensitive fields in this form only',

    'settings_enabled_display' => 'Enabled',
    'settings_enabled_instructions' => 'Enable or disable field encryption.',
    'settings_mask_display' => 'Mask String',
    'settings_mask_instructions' => 'The string shown to unauthorized users instead of the decrypted value.',
];
