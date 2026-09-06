<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Gdpr\PurgeExpiredContacts;
use App\Support\MultiTenancy\CurrentOrganization;

it('anonymise un contact inactif au-delà de la durée de conservation, mais pas un contact récent', function (): void {
    config(['gdpr.contact_retention_months' => 12]);
    [$organization] = organizationWithContactRole(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    $expired = Contact::factory()->for($organization)->create(['first_name' => 'Vieux', 'created_at' => now()->subMonths(24)]);
    $recent = Contact::factory()->for($organization)->create(['first_name' => 'Récent', 'created_at' => now()->subMonths(1)]);
    app(CurrentOrganization::class)->clear();

    $count = app(PurgeExpiredContacts::class)->handle();

    app(CurrentOrganization::class)->set($organization);
    expect(Contact::withTrashed()->find($expired->id)->trashed())->toBeTrue();
    expect(Contact::find($recent->id)->trashed())->toBeFalse();
    app(CurrentOrganization::class)->clear();

    expect($count)->toBe(1);
});
