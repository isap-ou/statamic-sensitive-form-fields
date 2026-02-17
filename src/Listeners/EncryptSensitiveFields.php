<?php

namespace Isapp\SensitiveFormFields\Listeners;

use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Events\SubmissionSaving;

class EncryptSensitiveFields
{
    public function __construct(
        protected FieldEncryptor $encryptor,
        protected SensitiveFieldResolver $resolver,
    ) {}

    public function handle(SubmissionSaving $event): void
    {
        if (! $this->encryptor->isEnabled()) {
            return;
        }

        $submission = $event->submission;
        $sensitiveHandles = $this->resolver->resolve($submission->form());

        foreach ($sensitiveHandles as $handle) {
            $value = $submission->get($handle);

            if (is_null($value) || $value === '') {
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $submission->set($handle, $this->encryptor->encrypt($value));
        }
    }
}
