<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * En dehors de Domain/Form (App\Http\Requests), donc libre de référencer
 * Event/EventType — contrairement à AttendeeIdentity et au reste de
 * Domain/Form, qui ne le peuvent jamais (section 3 du CLAUDE.md).
 */
final class SaveIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Format seulement (RFC), pas de vérification DNS/MX : contrairement
            // au champ "E-mail" du constructeur de formulaire (T-021, M2.1),
            // ce bloc identité fixe ne doit jamais dépendre de la résolution
            // DNS en sortie — recours réseau que cet environnement sandboxé a
            // révélé peu fiable même pour un domaine qui résout par ailleurs.
            'email' => ['required', 'email:rfc'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            // Obligatoire pour un événement personnel (mariage, anniversaire...
            // — accord explicite) : posé par ResolveGuestEvent avant que ce
            // Form Request ne soit résolu.
            'phone' => [$this->isPersonalEvent() ? 'required' : 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire pour ce type d\'événement.',
        ];
    }

    private function isPersonalEvent(): bool
    {
        /** @var Event $event */
        $event = $this->attributes->get('guestEvent');

        return $event->type->category() === EventCategory::Personal;
    }
}
