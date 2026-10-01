<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use Illuminate\Support\Str;

/**
 * Jeton du lien personnel d'un invité. Il porte son nom —
 * « cher-hordy-lusala-k3m9x2 » — pour qu'il se reconnaisse dès l'adresse,
 * et se termine par un tirage aléatoire : le nom seul se devinerait, le
 * tirage non.
 *
 * Le nom figure donc dans l'adresse : c'est voulu, et cela veut dire que
 * quiconque tient le lien lit le nom de son destinataire. Le lien se
 * renouvelle (RenewInvitationLink) si l'invité ne veut plus du sien.
 */
final class InvitationToken
{
    /**
     * Longueur du tirage aléatoire. Six caractères, soit plus de deux
     * milliards de combinaisons, avec un débit limité sur la route
     * d'ouverture : on ne les parcourt pas.
     */
    private const RANDOM_LENGTH = 6;

    private const MAX_NAME_LENGTH = 40;

    public static function for(?Contact $contact): string
    {
        $name = $contact === null ? '' : Str::slug(Str::limit($contact->fullName(), self::MAX_NAME_LENGTH, ''));
        $prefix = $name === '' ? 'invitation' : "cher-{$name}";

        do {
            $token = $prefix.'-'.Str::lower(Str::random(self::RANDOM_LENGTH));
        } while (EventInvitee::query()->withoutGlobalScopes()->where('invitation_token', $token)->exists());

        return $token;
    }
}
