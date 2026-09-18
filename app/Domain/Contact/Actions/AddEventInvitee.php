<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Data\InviteeData;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Ajout manuel à la liste d'invités d'un événement. Un contact déjà connu
 * par son e-mail est repris sans toucher à ses noms ; sans e-mail, un
 * nouveau contact est créé. Déjà invité, il est mis à jour plutôt que
 * dupliqué (règle 4.4).
 */
final class AddEventInvitee
{
    public function __construct(
        private readonly ResolveTagsByName $resolveTagsByName,
    ) {}

    public function handle(Organization $organization, int $eventId, User $user, InviteeData $data): EventInvitee
    {
        Gate::forUser($user)->authorize('updateGuests', $organization);

        return DB::transaction(function () use ($organization, $eventId, $data): EventInvitee {
            $contact = $this->contactFor($organization, $data);

            $invitee = EventInvitee::query()->updateOrCreate(
                ['event_id' => $eventId, 'contact_id' => $contact->id],
                [
                    'organization_id' => $organization->id,
                    'group_key' => $data->groupKey,
                    'companions_allowed' => $data->companionsAllowed,
                    'cc_email' => $data->ccEmail,
                ],
            );

            $tagIds = $this->resolveTagsByName->handle($organization->id, $data->tags);
            $contact->tags()->syncWithoutDetaching(array_fill_keys($tagIds, ['organization_id' => $organization->id]));

            return $invitee;
        });
    }

    private function contactFor(Organization $organization, InviteeData $data): Contact
    {
        $email = $data->email !== null ? mb_strtolower(trim($data->email)) : null;

        if ($email !== null && $email !== '') {
            $existing = Contact::query()->where('organization_id', $organization->id)->where('email', $email)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'email' => $email !== '' ? $email : null,
            'phone_e164' => $data->phone,
        ]);
    }
}
