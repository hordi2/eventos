<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

function organizationWithEventEditor(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('archive un événement depuis le tableau de bord', function (): void {
    [$organization, $admin] = organizationWithEventEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create();

    $response = $this->actingAs($admin)->post("/events/{$event->id}/archive");

    $response->assertRedirect();
    expect($event->fresh()->status)->toBe(EventStatus::Archived);
});

it('refuse d\'archiver à un rôle sans updateEvents', function (): void {
    [$organization, $doorstaff] = organizationWithEventEditor(MembershipRole::DoorStaff);
    $event = Event::factory()->for($organization)->published()->create();

    $response = $this->actingAs($doorstaff)->post("/events/{$event->id}/archive");

    $response->assertForbidden();
});

it('supprime un événement sans inscription', function (): void {
    [$organization, $admin] = organizationWithEventEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->delete("/events/{$event->id}");

    $response->assertRedirect(route('dashboard'));
    expect(Event::query()->find($event->id))->toBeNull();
});

it('refuse de supprimer un événement avec des inscriptions actives', function (): void {
    [$organization, $admin] = organizationWithEventEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = Form::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id, 'created_by' => $admin->id]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
    ]);

    $response = $this->actingAs($admin)->delete("/events/{$event->id}");

    $response->assertSessionHasErrors('status');
    expect(Event::query()->find($event->id))->not->toBeNull();
});

it('refuse de supprimer à un rôle sans deleteEvents', function (): void {
    [$organization, $editor] = organizationWithEventEditor(MembershipRole::Editor);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($editor)->delete("/events/{$event->id}");

    $response->assertForbidden();
});
