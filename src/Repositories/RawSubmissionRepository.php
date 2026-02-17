<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Repositories;

use Statamic\Contracts\Forms\SubmissionRepository;

/**
 * Marker interface used as a typed container key for the original (undecorated)
 * SubmissionRepository — i.e. the repository as it existed before the
 * DecryptingSubmissionRepository decorator was applied.
 *
 * Bound via app()->instance() inside ServiceProvider::decorateRepository().
 * Resolved via app(RawSubmissionRepository::class) in PRO commands.
 */
interface RawSubmissionRepository extends SubmissionRepository {}
