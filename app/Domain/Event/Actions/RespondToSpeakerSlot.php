<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Speaker;
use Carbon\CarbonImmutable;

/**
 * Réponse d'un intervenant à son créneau, depuis son portail. Il peut
 * revenir sur sa réponse tant que l'événement n'a pas eu lieu : seule la
 * dernière compte, d'où les deux dates remises à plat à chaque passage.
 */
final class RespondToSpeakerSlot
{
    public function handle(Speaker $speaker, bool $accepted, ?string $note = null): Speaker
    {
        $now = CarbonImmutable::now();

        $speaker->update([
            'confirmed_at' => $accepted ? $now : null,
            'declined_at' => $accepted ? null : $now,
            'response_note' => $note,
        ]);

        return $speaker;
    }
}
