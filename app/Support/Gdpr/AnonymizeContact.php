<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use App\Domain\Contact\Models\Contact;
use App\Domain\Form\Actions\DeleteRegistrationFile;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\RegistrationFile;
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
 * — signalé, pas traité par ce ticket. Seule exception : les fichiers joints,
 * conservés avec l'inscription et supprimés à l'effacement (décision
 * produit), nom gardé dans la réponse compris.
 *
 * Les commandes suivent, par un lien sûr : registration_id pour un don fait
 * depuis le formulaire, contact_id pour un achat de billets (posé par
 * LinkOrderToContact). Acheteur et donateur y sont effacés ; montants,
 * devise, statut, dates et paiements restent (lignes comptables, règle 4.5).
 * Les commandes antérieures à contact_id ne sont reliées à rien : un
 * rapprochement par e-mail serait trop fragile pour un effacement légal.
 */
final class AnonymizeContact
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly DeleteRegistrationFile $deleteRegistrationFile,
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

            foreach (RegistrationFile::query()->whereIn('registration_id', $registrationIds)->get() as $file) {
                $this->deleteRegistrationFile->handle($file);
            }

            DB::table('registration_answers')
                ->whereIn('registration_id', $registrationIds)
                ->whereIn('form_field_id', DB::table('form_fields')->where('type', FieldType::FileUpload->value)->select('id'))
                ->update(['value' => json_encode(['name' => 'Fichier supprimé']), 'updated_at' => now()]);

            // Sur la liste d'invités, l'e-mail en copie est une donnée
            // personnelle : effacé, et l'invitation retirée de la liste.
            DB::table('event_invitees')
                ->where('contact_id', $contact->id)
                ->whereNull('deleted_at')
                ->update(['cc_email' => null, 'deleted_at' => now(), 'updated_at' => now()]);

            $orderIds = DB::table('orders')
                ->where(fn ($query) => $query->whereIn('registration_id', $registrationIds)->orWhere('contact_id', $contact->id))
                ->pluck('id');

            DB::table('orders')
                ->whereIn('id', $orderIds)
                ->update([
                    // buyer_name et buyer_email sont NOT NULL : même
                    // traitement que registrations.email.
                    'buyer_name' => 'Invité anonymisé',
                    'buyer_email' => '',
                    'buyer_phone_e164' => null,
                    'updated_at' => now(),
                ]);

            DB::table('donations')
                ->whereIn('order_id', $orderIds)
                ->update([
                    'donor_name' => null,
                    'donor_company' => null,
                    'donor_address' => null,
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
