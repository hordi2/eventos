<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FieldOption;
use App\Support\Capacity\Actions\GetRemainingCapacity;

/**
 * « Option en rupture → griser l'option avec la mention "Complet" » (M2.4
 * du cahier des charges) — pas de liste d'attente au niveau d'une option,
 * contrairement à la capacité de l'événement (voir le docblock de
 * ReserveFieldOptionCapacity). Utilisé directement depuis le Blade invité
 * (_field.blade.php), comme DonationAnswer et FileUploadAnswer.
 */
final class FieldOptionAvailability
{
    public static function isFull(FieldOption $option): bool
    {
        return app(GetRemainingCapacity::class)->isFull('form_field_option', (string) $option->id, $option->quota);
    }
}
