<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\GuestList;

use App\Domain\Contact\Data\InviteeData;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Contact\Support\CompanionAllowance;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Ajout (POST) ou modification (PATCH) d'un invité depuis la liste. Le droit
 * (updateGuests) est vérifié par les actions ; l'accord d'envoi n'est exigé
 * que pour ajouter quelqu'un.
 */
final class SaveEventInviteeRequest extends FormRequest
{
    private ?string $phoneE164 = null;

    private ?int $companionsAllowed = 0;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'group_key' => ['nullable', 'string', 'max:50'],
            'companions' => ['nullable', 'string', 'max:20'],
            'cc_email' => ['nullable', 'email', 'max:255'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $hasFullName = trim((string) $this->input('first_name')) !== '' && trim((string) $this->input('last_name')) !== '';

            if (! $hasFullName && trim((string) $this->input('email')) === '') {
                $validator->errors()->add('first_name', 'Indiquez un nom complet (prénom et nom) ou une adresse e-mail.');
            }

            try {
                $this->companionsAllowed = CompanionAllowance::parse($this->input('companions'));
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('companions', $exception->getMessage());
            }

            $this->validatePhone($validator);
            $this->validateEmailOwnership($validator);

            if ($this->isMethod('post') && $this->organization()->sender_agreement_accepted_at === null) {
                $validator->errors()->add('agreement', "Acceptez d'abord l'accord d'envoi pour ajouter des invités.");
            }
        }];
    }

    public function toInviteeData(): InviteeData
    {
        $tags = $this->input('tags');

        return new InviteeData(
            firstName: $this->filled('first_name') ? trim((string) $this->input('first_name')) : null,
            lastName: $this->filled('last_name') ? trim((string) $this->input('last_name')) : null,
            email: $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
            phone: $this->phoneE164,
            groupKey: $this->filled('group_key') ? trim((string) $this->input('group_key')) : null,
            companionsAllowed: $this->companionsAllowed,
            ccEmail: $this->filled('cc_email') ? mb_strtolower(trim((string) $this->input('cc_email'))) : null,
            tags: is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [],
        );
    }

    private function validatePhone(Validator $validator): void
    {
        if (! $this->filled('phone')) {
            return;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse((string) $this->input('phone'), null);
        } catch (NumberParseException) {
            $number = null;
        }

        if ($number === null || ! $util->isValidNumber($number)) {
            $validator->errors()->add('phone', 'Numéro invalide : écrivez-le au format international, par exemple +243 81 234 5678.');

            return;
        }

        $this->phoneE164 = $util->format($number, PhoneNumberFormat::E164);
    }

    /**
     * En modification, l'e-mail corrige la fiche du contact : il ne peut pas
     * prendre celui d'un autre contact de l'organisation.
     */
    private function validateEmailOwnership(Validator $validator): void
    {
        $inviteeId = $this->route('invitee');

        if (! $this->isMethod('patch') || ! $this->filled('email') || $inviteeId === null) {
            return;
        }

        $contactId = EventInvitee::query()->whereKey((int) $inviteeId)->value('contact_id');
        $takenBy = Contact::query()->where('email', mb_strtolower(trim((string) $this->input('email'))))->value('id');

        if ($takenBy !== null && $takenBy !== $contactId) {
            $validator->errors()->add('email', 'Cette adresse appartient déjà à un autre contact de votre organisation.');
        }
    }

    private function organization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
