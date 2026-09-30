<?php

declare(strict_types=1);

namespace App\Support\Invitation;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;

/**
 * Invitation hébergée ailleurs (site d'un prestataire, page produite avec
 * un autre outil). Le lien personnel de l'invité y mène, avec trois
 * paramètres qui suffisent à personnaliser la page sans qu'elle ait à
 * connaître Itaza :
 *
 *   invitation  le jeton de l'invité, opaque
 *   qr          l'adresse de son code QR, à poser dans une balise <img>
 *   rsvp        l'adresse de sa page de réponse
 *
 * Aucune donnée personnelle ne voyage dans l'URL : le nom de l'invité
 * n'y figure pas, seul son jeton — qui ne dit rien de lui — est transmis.
 */
final class ExternalInvitationLink
{
    /**
     * Les paramètres tels que le site externe les recevra.
     *
     * @return array{invitation: string, qr: string, rsvp: string}
     */
    public function parameters(Event $event, EventInvitee $invitee): array
    {
        $event->loadMissing('organization');
        $route = fn (string $name): string => route($name, [
            $event->organization->slug,
            $event->slug,
            $invitee->invitation_token,
        ]);

        return [
            'invitation' => (string) $invitee->invitation_token,
            'qr' => $route('guest.registration.invitation.qr'),
            'rsvp' => $route('guest.registration.invitation.open'),
        ];
    }

    /**
     * L'adresse complète vers laquelle envoyer l'invité, paramètres
     * compris. Une adresse qui porte déjà des paramètres les garde.
     */
    public function urlFor(Event $event, EventInvitee $invitee): ?string
    {
        $base = $event->external_invitation_url;

        if ($base === null || $base === '') {
            return null;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query($this->parameters($event, $invitee));
    }

    /**
     * Exemple d'usage montré à l'organisateur, à transmettre à qui a fait
     * son site.
     */
    public function snippet(): string
    {
        return '<img src="{qr}" alt="Votre code d\'entrée">'."\n".'<a href="{rsvp}">Confirmer ma présence</a>';
    }
}
