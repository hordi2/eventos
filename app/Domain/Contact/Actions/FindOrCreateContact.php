<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;
use Carbon\CarbonImmutable;

/**
 * Pas d'autorisation ici : appelée par un listener système (§ le
 * Registration → Contact de T-040), jamais directement par une requête
 * organisateur — CreateContact/UpdateContact restent le seul chemin pour ça.
 */
final class FindOrCreateContact
{
    public function handle(int $organizationId, string $email, ?string $firstName, ?string $lastName, ?string $phone): Contact
    {
        $email = mb_strtolower(trim($email));

        $existing = Contact::query()
            ->where('organization_id', $organizationId)
            ->where('email', $email)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Contact::query()->create([
            'organization_id' => $organizationId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone_e164' => $phone,
            'email_consent' => true,
            'email_consent_source' => 'registration',
            'email_consent_at' => CarbonImmutable::now(),
        ]);
    }
}
