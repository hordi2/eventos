<?php

declare(strict_types=1);

namespace App\Domain\Form\Events;

use App\Domain\Form\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis après le commit d'une inscription réussie (confirmée ou en liste
 * d'attente). Portera plus tard la création/mise à jour du Contact (T-040),
 * l'e-mail de confirmation (M4) et les webhooks registration.created (M9) —
 * aucun listener n'existe encore.
 */
final class RegistrationCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Registration $registration,
    ) {}
}
