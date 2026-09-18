<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Models\EventAudience;
use App\Domain\Event\Models\EventType;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreateEventRequest extends FormRequest
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
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            // La RLS PostgreSQL (T-002) rend déjà invisible tout lieu d'une autre
            // organisation à cette requête : Rule::exists suffit, pas besoin de
            // filtrer explicitement sur organization_id ici.
            'venue_id' => ['nullable', 'integer', Rule::exists('venues', 'id')->whereNull('deleted_at')],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'required_with:venue_name'],
            'venue_access_instructions' => ['nullable', 'string'],
            'venue_parking_info' => ['nullable', 'string'],
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
