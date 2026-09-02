<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

/**
 * Choix appliqué uniformément à tout l'import (T-041) plutôt qu'un examen
 * ligne par ligne : simplification volontaire de M3.3.2 pour un premier
 * import fonctionnel — un doublon fusionne, s'ignore, ou force une
 * création selon ce seul réglage.
 */
enum DuplicateStrategy: string
{
    case Merge = 'merge';
    case Skip = 'skip';
    case CreateNew = 'create_new';
}
