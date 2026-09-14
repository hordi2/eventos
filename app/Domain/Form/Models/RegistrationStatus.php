<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

enum RegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    // Réponse « Je ne peux pas venir » : l'invité a répondu, sans prendre de
    // place. Distinct de Cancelled, qui annule une inscription confirmée.
    case Declined = 'declined';
}
