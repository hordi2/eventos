<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Models\EventType;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
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
}
