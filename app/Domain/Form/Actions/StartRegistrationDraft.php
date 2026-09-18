<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\RegistrationDraft;
use Illuminate\Support\Str;

final class StartRegistrationDraft
{
    /**
     * $eventInviteeId : invitation identifiée d'un événement réservé à sa
     * liste d'invités ; le brouillon la garde jusqu'à la soumission.
     */
    public function handle(int $organizationId, int $eventId, int $formVersionId, ?int $eventInviteeId = null): RegistrationDraft
    {
        return RegistrationDraft::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'event_invitee_id' => $eventInviteeId,
            'form_version_id' => $formVersionId,
            'resume_token' => Str::random(40),
        ]);
    }
}
