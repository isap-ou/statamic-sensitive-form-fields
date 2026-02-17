<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Repositories;

use Illuminate\Support\Facades\Auth;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\Submission;
use Statamic\Contracts\Forms\SubmissionQueryBuilder as SubmissionQueryBuilderContract;

class DecryptingSubmissionQueryBuilder implements SubmissionQueryBuilderContract
{
    public function __construct(
        protected SubmissionQueryBuilderContract $inner,
        protected FieldEncryptor $encryptor,
        protected SensitiveFieldResolver $resolver,
    ) {}

    public function get($columns = ['*'])
    {
        return $this->decryptCollection($this->inner->get($columns));
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $paginator = $this->inner->paginate($perPage, $columns, $pageName, $page);
        $paginator->setCollection($this->decryptCollection($paginator->getCollection()));

        return $paginator;
    }

    public function __call($method, $args)
    {
        $result = $this->inner->{$method}(...$args);

        if ($result === $this->inner) {
            return $this;
        }

        return $result;
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

        $canDecrypt = ! $this->encryptor->isPro() || $this->isAuthorized();

        foreach ($sensitiveHandles as $handle) {
            $value = $submission->get($handle);

            if (\is_null($value) || $value === '' || ! \is_string($value)) {
                continue;
            }

            if (! $this->encryptor->isEncrypted($value)) {
                continue;
            }

            if ($canDecrypt) {
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