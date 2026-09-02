<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

final class UpdateContact
{
    public function __construct(
        private readonly FindOrCreateHousehold $findOrCreateHousehold,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Contact $contact, User $updater, array $data): Contact
    {
        Gate::forUser($updater)->authorize('update', $contact);

        $householdId = $contact->household_id;

        if (array_key_exists('household_name', $data)) {
            $householdId = empty($data['household_name'])
                ? null
                : $this->findOrCreateHousehold->handle($contact->organization, $data['household_name'])->id;
        }

        $contact->update([
            'household_id' => $householdId,
            'first_name' => $data['first_name'] ?? $contact->first_name,
            'last_name' => $data['last_name'] ?? $contact->last_name,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : $contact->email,
            'phone_e164' => $data['phone_e164'] ?? $contact->phone_e164,
            'company' => $data['company'] ?? $contact->company,
            'job_title' => $data['job_title'] ?? $contact->job_title,
            'preferred_language' => $data['preferred_language'] ?? $contact->preferred_language,
            'preferred_channel' => $data['preferred_channel'] ?? $contact->preferred_channel,
            ...$this->consentChanges($contact, 'email', $data['email_consent'] ?? null),
            ...$this->consentChanges($contact, 'sms', $data['sms_consent'] ?? null),
            ...$this->consentChanges($contact, 'whatsapp', $data['whatsapp_consent'] ?? null),
        ]);

        return $contact->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function consentChanges(Contact $contact, string $channel, ?bool $newValue): array
    {
        if ($newValue === null) {
            return [];
        }

        $wasGranted = (bool) $contact->{"{$channel}_consent"};

        if ($newValue && ! $wasGranted) {
            return [
                "{$channel}_consent" => true,
                "{$channel}_consent_source" => 'organizer',
                "{$channel}_consent_at" => CarbonImmutable::now(),
            ];
        }

        // Révocation : on efface le booléen, mais jamais la date/source
        // d'origine — elle reste la trace de quand le consentement avait
        // été accordé (§4.5 du CLAUDE.md : jamais de suppression directe).
        return ["{$channel}_consent" => $newValue];
    }
}
