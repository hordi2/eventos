<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

enum ExportType: string
{
    case Contacts = 'contacts';
    case Registrations = 'registrations';
    case Orders = 'orders';
    case CheckIns = 'checkins';

    public function label(): string
    {
        return match ($this) {
            self::Contacts => 'Invités',
            self::Registrations => 'Inscriptions',
            self::Orders => 'Commandes',
            self::CheckIns => 'Check-ins',
        };
    }
}
