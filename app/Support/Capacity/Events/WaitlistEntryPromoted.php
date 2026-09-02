<?php

declare(strict_types=1);

namespace App\Support\Capacity\Events;

use App\Support\Capacity\Models\WaitlistEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis quand une place libérée fait passer le premier de la liste d'attente
 * en réservation confirmée. L'envoi effectif de la notification (e-mail,
 * WhatsApp) revient à un listener du module Messaging (M4, pas encore
 * construit) : cet événement se contente de porter le fait métier.
 */
final class WaitlistEntryPromoted
{
    use Dispatchable;

    public function __construct(
        public readonly WaitlistEntry $entry,
    ) {}
}
