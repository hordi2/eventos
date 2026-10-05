<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Models\EventAudience;
use App\Domain\Event\Models\EventType;
use App\Support\Guest\GuestLocales;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(EventType::class)],
            'audience' => ['nullable', Rule::enum(EventAudience::class)],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date'],
            'requires_approval' => ['boolean'],
            'locale' => ['nullable', 'string', Rule::in(array_keys(GuestLocales::SUPPORTED))],
            // Vide : aucune limite de places, comme pour une session (T-024).
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'allow_waitlist' => ['boolean'],
            // Annuaire des participants (D8).
            'has_attendee_directory' => ['boolean'],
            'has_attendee_messaging' => ['boolean'],
            // Empreinte carbone (D12).
            'has_carbon_report' => ['boolean'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            // La RLS PostgreSQL (T-002) rend déjà invisible tout lieu d'une autre
            // organisation à cette requête : Rule::exists suffit, pas besoin de
            // filtrer explicitement sur organization_id ici.
            'venue_id' => ['nullable', 'integer', Rule::exists('venues', 'id')->whereNull('deleted_at')],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'required_with:venue_name'],
            'venue_access_instructions' => ['nullable', 'string'],
            'venue_parking_info' => ['nullable', 'string'],
            // Invitation hébergée ailleurs : une adresse complète, en https
            // — un site d'invitation se visite depuis un téléphone, et un
            // lien non chiffré y est bloqué par les navigateurs modernes.
            'external_invitation_url' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'external_invitation_url.url' => "L'adresse de votre site d'invitation doit être complète, par exemple https://mon-mariage.example.",
            'external_invitation_url.starts_with' => "L'adresse doit commencer par https://.",
        ];
    }

    /**
     * « after:registration_opens_at » échouerait quand l'ouverture est vide :
     * la comparaison ne vaut que si les deux bornes sont fixées.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $opensAt = $this->input('registration_opens_at');
            $closesAt = $this->input('registration_closes_at');

            if (is_string($opensAt) && is_string($closesAt) && $opensAt !== '' && $closesAt !== '' && strtotime($closesAt) <= strtotime($opensAt)) {
                $validator->errors()->add('registration_closes_at', 'La fermeture des inscriptions doit venir après leur ouverture.');
            }
        }];
    }
}
