<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FieldOption;

/**
 * Le comptage réel des sélections déjà faites viendra du module Inscriptions
 * (T-030, qui n'existe pas encore) : cette classe reçoit ce compte en
 * paramètre plutôt que de l'interroger elle-même, pour rester utilisable dès
 * aujourd'hui et sans dépendre d'un module qui n'existe pas.
 */
final class OptionQuota
{
    public function hasRemainingCapacity(FieldOption $option, int $alreadySelected): bool
    {
        if ($option->quota === null) {
            return true;
        }

        return $alreadySelected < $option->quota;
    }

    public function remaining(FieldOption $option, int $alreadySelected): ?int
    {
        if ($option->quota === null) {
            return null;
        }

        return max(0, $option->quota - $alreadySelected);
    }
}
