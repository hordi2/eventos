<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateContact
{
    public function __construct(
        private readonly FindOrCreateHousehold $findOrCreateHousehold,
        private readonly SyncContactTags $syncContactTags,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, User $creator, array $data): Contact
    {
        Gate::forUser($creator)->authorize('create', [Contact::class, $organization]);

        $householdId = null;

        if (! empty($data['household_name'])) {
            $householdId = $this->findOrCreateHousehold->handle($organization, $data['household_name'])->id;
        }

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'household_id' => $householdId,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            'phone_e164' => $data['phone_e164'] ?? null,
            'company' => $data['company'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
            'preferred_channel' => $data['preferred_channel'] ?? null,
        ]);

        $this->syncContactTags->handle($contact, $data['tag_ids'] ?? []);

        return $contact;
    }
}
