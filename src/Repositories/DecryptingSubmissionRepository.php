<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Repositories;

use Illuminate\Support\Facades\Auth;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Addons\Addon;
use Statamic\Contracts\Forms\Submission;
use Statamic\Contracts\Forms\SubmissionRepository;

class DecryptingSubmissionRepository implements SubmissionRepository
{
    public function __construct(
        protected SubmissionRepository $repository,
        protected FieldEncryptor $encryptor,
        protected SensitiveFieldResolver $resolver,
        protected Addon $addon,
    ) {}

    public function all()
    {
        return $this->decryptCollection($this->repository->all());
    }

    public function whereForm(string $handle)
    {
        return $this->decryptCollection($this->repository->whereForm($handle));
    }

    public function whereInForm(array $handles)
    {
        return $this->decryptCollection($this->repository->whereInForm($handles));
    }

    public function find($id)
    {
        $submission = $this->repository->find($id);

        if ($submission) {
            $this->decryptSubmission($submission);
        }

        return $submission;
    }

    public function make()
    {
        return $this->repository->make();
    }

    public function query()
    {
        return new DecryptingSubmissionQueryBuilder(
            $this->repository->query(),
            $this->encryptor,
            $this->resolver,
            $this->addon,
        );
    }

    public function save($entry)
    {
        return $this->repository->save($entry);
    }

    public function delete($entry)
    {
        return $this->repository->delete($entry);
    }

    protected function decryptCollection($submissions)
    {
        return $submissions->each(fn ($submission) => $this->decryptSubmission($submission));
    }

    protected function decryptSubmission(Submission $submission): void
    {
        if (! $this->encryptor->isEnabled()) {
            return;
        }

        $sensitiveHandles = $this->resolver->resolve($submission->form());

        if (empty($sensitiveHandles)) {
            return;
        }

        // FREE: all authenticated users can read decrypted values.
        // PRO: only super admins and users with the global or per-form permission can.
        $canDecrypt = $this->addon->edition() !== 'pro' || $this->isAuthorizedForForm($submission->form()->handle());

        foreach ($sensitiveHandles as $handle) {
            $value = $submission->get($handle);

            if (\is_null($value) || $value === '' || ! \is_string($value)) {
                continue;
            }

            if (! $this->encryptor->isEncrypted($value)) {
                continue;
            }

            if ($canDecrypt) {
                $submission->set($handle, $this->encryptor->decrypt($value, $submission->form()->handle()));
            } else {
                $submission->set($handle, $this->encryptor->mask());
            }
        }
    }

    protected function isAuthorizedForForm(string $formHandle): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Super admins bypass the explicit permission check.
        if ($user->isSuper()) {
            return true;
        }

        // Global permission acts as a wildcard across all forms.
        if ($user->hasPermission('view decrypted sensitive fields')) {
            return true;
        }

        // Per-form permission grants access to this specific form only.
        return $user->hasPermission("view decrypted {$formHandle} sensitive fields");
    }
}
