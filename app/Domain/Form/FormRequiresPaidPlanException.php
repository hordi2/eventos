<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

/**
 * Décision produit : les questions avancées se testent librement au plan
 * Gratuit, mais publier un formulaire qui en contient demande un plan payant.
 */
final class FormRequiresPaidPlanException extends RuntimeException
{
    /**
     * @param  list<string>  $fieldLabels
     */
    public static function forFields(array $fieldLabels): self
    {
        return new self(
            'Ce formulaire contient des questions réservées aux plans payants ('
            .implode(', ', $fieldLabels)
            .') : passez à un plan payant pour le publier, ou retirez ces questions.'
        );
    }
}
