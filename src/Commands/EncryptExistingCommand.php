<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Commands;

use Illuminate\Console\Command;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Repositories\RawSubmissionRepository;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Facades\Form;

class EncryptExistingCommand extends Command
{
    protected $signature = 'sensitive-fields:encrypt-existing
                            {--form= : Only process submissions from this form handle}
                            {--dry-run : Preview changes without persisting}';

    protected $description = '[PRO] Encrypt plaintext values of sensitive fields in existing form submissions.';

    public function __construct(
        protected FieldEncryptor $encryptor,
        protected SensitiveFieldResolver $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Resolving SubmissionRepository triggers the extend callback, which binds RawSubmissionRepository.
        app(SubmissionRepository::class);
        $rawRepo = app(RawSubmissionRepository::class);

        $isDryRun = (bool) $this->option('dry-run');
        $formFilter = $this->option('form');

        $forms = Form::all();

        if ($formFilter) {
            $forms = $forms->filter(fn ($form) => $form->handle() === $formFilter);

            if ($forms->isEmpty()) {
                $this->error("Form '{$formFilter}' not found.");

                return self::FAILURE;
            }
        }

        $totalProcessed = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($forms as $form) {
            $handle = $form->handle();
            $sensitiveHandles = $this->resolver->resolve($form);

            if (empty($sensitiveHandles)) {
                $this->line("Form [{$handle}]: no sensitive fields, skipping.");

                continue;
            }

            $submissions = $rawRepo->whereForm($handle);
            $this->line("Form [{$handle}]: processing {$submissions->count()} submissions...");

            foreach ($submissions as $submission) {
                $totalProcessed++;
                $needsSave = false;

                try {
                    foreach ($sensitiveHandles as $field) {
                        $value = $submission->get($field);

                        if (\is_null($value) || $value === '' || ! \is_string($value)) {
                            $totalSkipped++;

                            continue;
                        }

                        if ($this->encryptor->isEncrypted($value)) {
                            $totalSkipped++;

                            continue;
                        }

                        if (! $isDryRun) {
                            $submission->set($field, $this->encryptor->encrypt($value));
                        }

                        $needsSave = true;
                    }

                    if ($needsSave && ! $isDryRun) {
                        $rawRepo->save($submission);
                    }

                    if ($needsSave) {
                        $totalUpdated++;
                    }
                } catch (\Throwable $e) {
                    $totalErrors++;
                    $this->warn("Error processing submission [{$submission->id()}]: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $prefix = $isDryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Done. Processed: {$totalProcessed}, Updated: {$totalUpdated}, Skipped: {$totalSkipped}, Errors: {$totalErrors}");

        return self::SUCCESS;
    }
}
