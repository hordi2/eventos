<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Gdpr\PurgeExpiredContacts;
use Illuminate\Console\Command;

final class PurgeExpiredGdprContacts extends Command
{
    protected $signature = 'gdpr:purge-expired-contacts';

    protected $description = 'Anonymise les contacts inactifs au-delà de la durée de conservation configurée (T-075)';

    public function handle(PurgeExpiredContacts $action): int
    {
        $count = $action->handle();

        $this->info("{$count} contact(s) anonymisé(s).");

        return self::SUCCESS;
    }
}
