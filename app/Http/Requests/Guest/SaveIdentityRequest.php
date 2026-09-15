<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * En dehors de Domain/Form (App\Http\Requests), donc libre de référencer
 * Event/EventType — contrairement à AttendeeIdentity et au reste de
 * Domain/Form, qui ne le peuvent jamais (section 3 du CLAUDE.md).
 */
final class SaveIdentityRequest extends FormRequest
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $formSettings = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sans JavaScript, toutes les lignes d'accompagnant s'affichent : une
     * ligne laissée entièrement vide n'est pas un accompagnant.
     */
    protected function prepareForValidation(): void
    {
        $companions = $this->input(CompanionData::INPUT_KEY);

        if (! is_array($companions)) {
            return;
        }

        $filled = array_filter(
            $companions,
            fn (mixed $row): bool => is_array($row)
                && (trim((string) ($row['first_name'] ?? '')) !== '' || trim((string) ($row['last_name'] ?? '')) !== ''),
        );

        $this->merge([CompanionData::INPUT_KEY => array_values($filled)]);
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
            // « Je serai présent(e) » / « Je ne peux pas venir » : demandé
            // seulement si l'organisateur propose le refus.
            'attending' => [$this->settings()['rsvp']['decline_enabled'] ? 'required' : 'nullable', 'boolean'],
            CompanionData::INPUT_KEY => ['nullable', 'array', 'max:'.(int) $this->settings()['rsvp']['max_companions']],
            CompanionData::INPUT_KEY.'.*.first_name' => ['required', 'string', 'max:255'],
            CompanionData::INPUT_KEY.'.*.last_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire pour ce type d\'événement.',
            'attending.required' => 'Indiquez si vous serez présent(e).',
            CompanionData::INPUT_KEY.'.max' => 'Vous pouvez venir avec :max accompagnant(s) au plus.',
            CompanionData::INPUT_KEY.'.*.first_name.required' => 'Indiquez le prénom de chaque accompagnant.',
        ];
    }

    private function isPersonalEvent(): bool
    {
        return $this->guestEvent()->type->category() === EventCategory::Personal;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function settings(): array
    {
        if ($this->formSettings === null) {
            $form = Form::query()->where('event_id', $this->guestEvent()->id)->first();
            $this->formSettings = FormSettings::resolve($form?->settings);
        }

        return $this->formSettings;
    }

    private function guestEvent(): Event
    {
        /** @var Event $event */
        $event = $this->attributes->get('guestEvent');

        return $event;
    }
}
