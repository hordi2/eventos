<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Traverse Contact (Domain/Contact) et Form (Domain/Form, registrations et
 * attendees) : ne peut vivre dans Domain/Contact (section 3 du CLAUDE.md),
 * même raisonnement que ComputeEventSegmentContacts.
 *
 * Droit à l'effacement (T-075, RGPD §10.2 P-04) : une anonymisation, jamais
 * une suppression physique (règle 4.5 du CLAUDE.md) — les lignes restent
 * (agrégats, statistiques d'événement, historique de présence), seules les
 * données identifiantes sont effacées. Les réponses de formulaire libres
 * (RegistrationAnswer) ne sont volontairement pas parcourues : un champ
 * personnalisé peut contenir n'importe quoi, aucune façon fiable d'y
 * détecter une identité sans risquer d'effacer une réponse métier légitime
 * — signalé, pas traité par ce ticket.
 *
 * N'anonymise pas les commandes de billetterie (Order.buyer_*) : Domain/Ticketing
 * n'a aucune colonne reliant une commande à un Contact (section 3 du
 * CLAUDE.md), seul un rapprochement par e-mail serait possible, trop
 * fragile pour un effacement légal (faux positifs/négatifs) — signalé
 * comme limite connue plutôt que construit sur une heuristique.
 */
final class AnonymizeContact
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Contact $contact, User $user): Contact
    {
        Gate::forUser($user)->authorize('updateGuests', $contact->organization);

        DB::transaction(function () use ($contact): void {
            $registrationIds = DB::table('registrations')->where('contact_id', $contact->id)->pluck('id');

            DB::table('attendees')
                ->whereIn('registration_id', $registrationIds)
                ->update([
                    'first_name' => 'Invité',
                    'last_name' => 'anonymisé',
                    'email' => null,
                    'updated_at' => now(),
                ]);

            DB::table('registrations')
                ->whereIn('id', $registrationIds)
                ->update([
                    'first_name' => 'Invité',
                    'last_name' => 'anonymisé',
                    // Contrairement à Contact/Attendee, "email" est NOT NULL
                    // sur registrations (la soumission RSVP l'exige toujours)
                    // — chaîne vide plutôt que nulle pour la même anonymisation.
                    'email' => '',
                    'phone_e164' => null,
                    'ip_address' => null,
                    'user_agent' => null,
                    'updated_at' => now(),
                ]);

            $contact->forceFill([
                'first_name' => 'Contact',
                'last_name' => 'anonymisé',
                'email' => null,
                'phone_e164' => null,
                'company' => null,
                'job_title' => null,
                'custom_fields' => null,
                'email_consent' => false,
                'sms_consent' => false,
                'whatsapp_consent' => false,
            ])->save();

            $contact->delete();
        });

        $this->recordAuditLog->handle(
            action: 'contact.anonymized',
            causer: $user,
            subject: $contact,
        );

        return $contact;
    }
}
