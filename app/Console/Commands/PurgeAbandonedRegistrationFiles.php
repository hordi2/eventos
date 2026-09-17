<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Registration\PurgeUnclaimedRegistrationFiles;
use Illuminate\Console\Command;

final class PurgeAbandonedRegistrationFiles extends Command
{
    protected $signature = 'registration-files:purge-unclaimed';

    protected $description = 'Supprime les fichiers joints jamais rattachés à une inscription, 30 jours après leur envoi';

    public function handle(PurgeUnclaimedRegistrationFiles $action): int
    {
        $count = $action->handle();

        $this->info("{$count} fichier(s) joint(s) supprimé(s).");

        return self::SUCCESS;
    }
}
