<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Analyse antivirus d'un fichier joint (S-06 du CDC) : seul un fichier sain
 * se télécharge.
 */
enum FileScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    // ClamAV n'a pas répondu, même après toutes les tentatives.
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Analyse en cours',
            self::Clean => 'Sain',
            self::Infected => 'Refusé : virus détecté',
            self::Failed => 'Analyse impossible',
        };
    }
}
