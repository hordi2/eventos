<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Les 12 types MVP du CDC (M2.1). La validation, le rendu et l'export
 * propres à chaque type sont ajoutés par T-021 ; cet enum ne fait que
 * nommer le vocabulaire partagé dont Form/FormField ont besoin pour
 * exister dès T-020.
 */
enum FieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
    case Date = 'date';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case YesNo = 'yes_no';
    case Consent = 'consent';
    case MealChoice = 'meal_choice';
    case InformationalText = 'informational_text';

    public function supportsOptions(): bool
    {
        return match ($this) {
            self::SingleChoice, self::MultipleChoice, self::MealChoice => true,
            default => false,
        };
    }
}
