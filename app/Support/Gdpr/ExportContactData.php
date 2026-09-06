<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use App\Domain\Contact\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * Droit d'accès et portabilité (T-075, RGPD §10.2 P-03/P-06) : « export des
 * données d'une personne en un clic », « au format machine (JSON) ».
 * Traverse Contact (Domain/Contact) et Form (Domain/Form) : vit hors des
 * deux (section 3 du CLAUDE.md), même raisonnement que AnonymizeContact.
 *
 * Les réponses de formulaire libres sont incluses telles quelles (question
 * → réponse) : c'est justement ce que la portabilité RGPD demande de
 * pouvoir restituer à la personne concernée, contrairement à
 * AnonymizeContact qui évite volontairement d'y toucher en écriture.
 *
 * N'inclut pas les commandes de billetterie : même limite que
 * AnonymizeContact, aucune colonne ne relie une commande à un Contact.
 */
final class ExportContactData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Contact $contact): array
    {
        return [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone_e164' => $contact->phone_e164,
                'company' => $contact->company,
                'job_title' => $contact->job_title,
                'preferred_language' => $contact->preferred_language,
                'preferred_channel' => $contact->preferred_channel,
                'custom_fields' => $contact->custom_fields,
                'created_at' => $contact->created_at?->toIso8601String(),
            ],
            'consentements' => [
                'email' => ['accordé' => $contact->email_consent, 'source' => $contact->email_consent_source, 'horodatage' => $contact->email_consent_at?->toIso8601String()],
                'sms' => ['accordé' => $contact->sms_consent, 'source' => $contact->sms_consent_source, 'horodatage' => $contact->sms_consent_at?->toIso8601String()],
                'whatsapp' => ['accordé' => $contact->whatsapp_consent, 'source' => $contact->whatsapp_consent_source, 'horodatage' => $contact->whatsapp_consent_at?->toIso8601String()],
                'désinscrit_le' => $contact->unsubscribed_at?->toIso8601String(),
            ],
            'tags' => $contact->tags()->pluck('name'),
            'foyer' => $contact->household?->only(['id', 'name']),
            'inscriptions' => $this->registrations($contact),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrations(Contact $contact): array
    {
        $registrations = DB::table('registrations')
            ->where('contact_id', $contact->id)
            ->get(['id', 'event_id', 'status', 'registered_at', 'created_at']);

        return $registrations->map(function (object $registration): array {
            $answers = DB::table('registration_answers')
                ->join('form_fields', 'form_fields.id', '=', 'registration_answers.form_field_id')
                ->where('registration_answers.registration_id', $registration->id)
                ->get(['form_fields.label', 'registration_answers.value'])
                ->mapWithKeys(fn (object $answer): array => [$answer->label => json_decode((string) $answer->value, true)]);

            $attendees = DB::table('attendees')
                ->where('registration_id', $registration->id)
                ->get(['first_name', 'last_name', 'email', 'is_primary', 'checked_in_at']);

            return [
                'événement_id' => $registration->event_id,
                'statut' => $registration->status,
                'inscrit_le' => $registration->registered_at,
                'réponses' => $answers,
                'participants' => $attendees,
            ];
        })->all();
    }
}
