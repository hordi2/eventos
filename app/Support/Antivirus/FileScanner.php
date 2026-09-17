<?php

declare(strict_types=1);

namespace App\Support\Antivirus;

/**
 * Analyse antivirus d'un fichier (S-06 du CDC). ClamAvScanner en
 * production ; les tests lient une implémentation factice.
 */
interface FileScanner
{
    /**
     * @param  resource  $stream  contenu du fichier, lu jusqu'au bout
     *
     * @throws ScannerUnavailableException quand l'analyse n'a pas pu avoir lieu
     */
    public function scan($stream): ScanResult;
}
