<?php

declare(strict_types=1);

use App\Domain\Event\Actions\SetEventPassword;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

function organizationWithEditorForPassword(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('protège un événement par mot de passe', function (): void {
    [$organization, $admin] = organizationWithEditorForPassword(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['access_mode' => EventAccessMode::Public]);

    $updated = app(SetEventPassword::class)->handle($event, $admin, 'un-mot-de-passe');

    expect($updated->access_mode)->toBe(EventAccessMode::Password);
    expect(Hash::check('un-mot-de-passe', (string) $updated->password_hash))->toBeTrue();
});

it('retire la protection par mot de passe quand le mot de passe est vide', function (): void {
    [$organization, $admin] = organizationWithEditorForPassword(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create([
        'access_mode' => EventAccessMode::Password,
        'password_hash' => Hash::make('ancien-mot-de-passe'),
    ]);

    $updated = app(SetEventPassword::class)->handle($event, $admin, null);

    expect($updated->access_mode)->toBe(EventAccessMode::Public);
    expect($updated->password_hash)->toBeNull();
});

it('réserve l\'accès au mot de passe même si l\'événement était jusque-là réservé à sa liste', function (): void {
    [$organization, $admin] = organizationWithEditorForPassword(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['access_mode' => EventAccessMode::ClosedList]);

    $updated = app(SetEventPassword::class)->handle($event, $admin, 'un-mot-de-passe');

    expect($updated->access_mode)->toBe(EventAccessMode::Password);
});

it('refuse de poser un mot de passe à un rôle qui n\'a pas la capacité updateEvents', function (): void {
    [$organization, $doorstaff] = organizationWithEditorForPassword(MembershipRole::DoorStaff);
    $event = Event::factory()->for($organization)->create();

    app(SetEventPassword::class)->handle($event, $doorstaff, 'un-mot-de-passe');
})->throws(AuthorizationException::class);

it('active la protection par mot de passe depuis la liste d\'invités', function (): void {
    [$organization, $admin] = organizationWithEditorForPassword(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['access_mode' => EventAccessMode::Public]);

    $response = $this->actingAs($admin)->patch("/events/{$event->id}/guest-list/password", [
        'password' => 'un-mot-de-passe',
    ]);

    $response->assertRedirect();
    expect($event->fresh()->access_mode)->toBe(EventAccessMode::Password);
});

it('refuse un mot de passe trop court depuis la liste d\'invités', function (): void {
    [$organization, $admin] = organizationWithEditorForPassword(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->patch("/events/{$event->id}/guest-list/password", [
        'password' => 'abc',
    ]);

    $response->assertSessionHasErrors('password');
});

it('retire le mot de passe depuis la liste d\'invités quand il est envoyé vide', function (): void {
    [$organization, $admin] = organizationWithEditorForPassword(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create([
        'access_mode' => EventAccessMode::Password,
        'password_hash' => Hash::make('ancien-mot-de-passe'),
    ]);

    $response = $this->actingAs($admin)->patch("/events/{$event->id}/guest-list/password", [
        'password' => '',
    ]);

    $response->assertRedirect();
    expect($event->fresh()->access_mode)->toBe(EventAccessMode::Public);
});
