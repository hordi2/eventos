<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Data\InviteeData;
use App\Domain\Contact\Models\EventInvitee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Modification depuis la liste d'invités : l'identité corrige la fiche du
 * contact (partagée par tous les événements de l'organisation), le reste
 * ne concerne que cette invitation. Les tags donnés remplacent ceux du
 * contact : le formulaire les montre tous.
 */
final class UpdateEventInvitee
{
    public function __construct(
        private readonly ResolveTagsByName $resolveTagsByName,
        private readonly SyncContactTags $syncContactTags,
    ) {}

    public function handle(EventInvitee $invitee, User $user, InviteeData $data): EventInvitee
    {
        $invitee->loadMissing('contact.organization');
        Gate::forUser($user)->authorize('updateGuests', $invitee->contact->organization);

        return DB::transaction(function () use ($invitee, $data): EventInvitee {
            $email = $data->email !== null ? mb_strtolower(trim($data->email)) : null;

            $invitee->contact->update([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'email' => $email !== '' ? $email : null,
                'phone_e164' => $data->phone,
            ]);

            $invitee->update([
                'group_key' => $data->groupKey,
                'companions_allowed' => $data->companionsAllowed,
                'cc_email' => $data->ccEmail,
            ]);

            $this->syncContactTags->handle($invitee->contact, $this->resolveTagsByName->handle($invitee->organization_id, $data->tags));

            return $invitee->refresh();
        });
    }
}
