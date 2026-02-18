<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Facades\Form;

class RekeyCommand extends Command
{
    protected $signature = 'sensitive-fields:rekey
                            {--old-key= : The previous APP_KEY value (base64:<key> format, as stored in .env)}
                            {--form= : Only process submissions from this form handle}
                            {--dry-run : Preview changes without persisting}';

    protected $description = '[PRO] Re-encrypt sensitive field values from an old APP_KEY to the current one.';

    public function __construct(
        protected FieldEncryptor $encryptor,
        protected SensitiveFieldResolver $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $oldKeyString = $this->option('old-key')
            ?? $this->secret('Enter the previous APP_KEY (base64:... format, as stored in your .env)');

        if (! $oldKeyString) {
            $this->error('No key provided.');

            return self::FAILURE;
        }

        $oldEncrypter = $this->buildEncrypter($oldKeyString);

        if ($oldEncrypter === null) {
            return self::FAILURE;
        }

        // Resolving SubmissionRepository triggers the extend callback, which binds RawSubmissionRepository.
        app(SubmissionRepository::class);
        $rawRepo = app(\Isapp\SensitiveFormFields\Repositories\RawSubmissionRepository::class);

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

                        if (! $this->encryptor->isEncrypted($value)) {
                            $totalSkipped++;

                            continue;
                        }

                        try {
                            $plain = $oldEncrypter->decryptString(substr($value, \strlen('enc:v1:')));
                        } catch (DecryptException $e) {
                            // Check whether the value is already encrypted with the current key
                            // (e.g. the command is re-run, or some submissions were saved after
                            // APP_KEY rotation). If so, count as skipped — not an error.
                            try {
                                \Illuminate\Support\Facades\Crypt::decryptString(substr($value, \strlen('enc:v1:')));
                                $totalSkipped++;
                            } catch (\Throwable) {
                                $totalErrors++;
                                $this->warn("Could not decrypt [{$field}] in submission [{$submission->id()}]: {$e->getMessage()}");
                            }

                            continue;
                        }

                        if (! $isDryRun) {
                            $submission->set($field, $this->encryptor->encrypt($plain));
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

    private function buildEncrypter(string $keyString): ?Encrypter
    {
        try {
            if (str_starts_with($keyString, 'base64:')) {
                $rawKey = base64_decode(substr($keyString, 7), strict: true);

                if ($rawKey === false) {
                    $this->error('Invalid --old-key value: could not base64-decode the key.');

                    return null;
                }
            } else {
                $rawKey = $keyString;
            }

            return new Encrypter($rawKey, config('app.cipher', 'AES-256-CBC'));
        } catch (\RuntimeException $e) {
            $this->error('Invalid --old-key: ' . $e->getMessage());

            return null;
        }
    }
}
