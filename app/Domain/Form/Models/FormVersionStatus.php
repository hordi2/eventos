<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * draft   : en cours de conception, jamais publiée, peut être modifiée en
 *           place (aucune réponse ne peut encore y être interprétée).
 * published : la version actuellement utilisée pour les nouvelles soumissions
 *           (Form::current_version_id) ; figée, plus jamais modifiée.
 * archived : une ancienne version publiée, remplacée par une plus récente ;
 *           conservée intacte pour interpréter les réponses déjà collectées.
 */
enum FormVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
