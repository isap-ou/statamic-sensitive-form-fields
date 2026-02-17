<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Repositories;

use Illuminate\Support\Facades\Auth;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\Submission;
use Statamic\Contracts\Forms\SubmissionRepository;

class DecryptingSubmissionRepository implements SubmissionRepository
{
    public function __construct(
        protected SubmissionRepository $repository,
        protected FieldEncryptor $encryptor,
        protected SensitiveFieldResolver $resolver,
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
        return $this->repository->query();
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

        $authorized = $this->isAuthorized();

        foreach ($sensitiveHandles as $handle) {
            $value = $submission->get($handle);

            if (\is_null($value) || $value === '' || ! \is_string($value)) {
                continue;
            }

            if (! $this->encryptor->isEncrypted($value)) {
                continue;
            }

            if ($authorized) {
                $submission->set($handle, $this->encryptor->decrypt($value));
            } else {
                $submission->set($handle, $this->encryptor->mask());
            }
        }
    }

    protected function isAuthorized(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuper()) {
            return true;
        }

        return $user->hasPermission('view decrypted sensitive fields');
    }
}
