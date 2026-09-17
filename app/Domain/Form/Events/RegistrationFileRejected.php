<?php

declare(strict_types=1);

namespace App\Domain\Form\Events;

use App\Domain\Form\Models\RegistrationFile;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis quand ClamAV déclare un fichier joint infecté (RecordFileScanResult),
 * une fois le fichier supprimé du disque.
 */
final class RegistrationFileRejected
{
    use Dispatchable;

    public function __construct(
        public readonly RegistrationFile $file,
    ) {}
}
