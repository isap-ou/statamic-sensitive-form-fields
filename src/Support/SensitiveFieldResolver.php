<?php

namespace Isapp\SensitiveFormFields\Support;

use Statamic\Contracts\Forms\Form;

class SensitiveFieldResolver
{
    protected array $cache = [];

    public function resolve(Form $form): array
    {
        $handle = $form->handle();

        if (isset($this->cache[$handle])) {
            return $this->cache[$handle];
        }

        $sensitiveHandles = $form->blueprint()
            ->fields()
            ->all()
            ->filter(fn ($field) => $field->get('sensitive') === true)
            ->keys()
            ->all();

        return $this->cache[$handle] = $sensitiveHandles;
    }
}
