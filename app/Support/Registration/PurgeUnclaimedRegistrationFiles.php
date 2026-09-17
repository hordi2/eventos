<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Form\Actions\DeleteRegistrationFile;
use App\Domain\Form\Models\RegistrationFile;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

/**
 * Fichiers joints jamais rattachés à une inscription (brouillon abandonné,
 * modification jamais enregistrée) : supprimés passé
 * FileUploadAnswer::UNCLAIMED_RETENTION_DAYS jours. Parcourt toutes les
 * organisations, d'où Support — même principe que PurgeExpiredContacts.
 */
final class PurgeUnclaimedRegistrationFiles
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly DeleteRegistrationFile $deleteRegistrationFile,
    ) {}

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDays(FileUploadAnswer::UNCLAIMED_RETENTION_DAYS);
        $count = 0;

        foreach (Organization::query()->withoutGlobalScopes()->get() as $organization) {
            $this->currentOrganization->set($organization);

            $files = RegistrationFile::query()
                ->whereNull('registration_id')
                ->where('created_at', '<', $cutoff)
                ->get();

            foreach ($files as $file) {
                $this->deleteRegistrationFile->handle($file);
                $count++;
            }
        }

        $this->currentOrganization->clear();

        return $count;
    }
}
