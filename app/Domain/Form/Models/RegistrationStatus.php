<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

enum RegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
}
