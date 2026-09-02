<?php

declare(strict_types=1);

namespace App\Domain\Form\Events;

use App\Domain\Form\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

final class RegistrationUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Registration $registration,
    ) {}
}
