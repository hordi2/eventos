<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorEventPermission;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Mail\CollaboratorInvitationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function collaboratorPayload(Event $event, CollaboratorPermission $permission, string $email = 'equipe@example.test'): array
{
    return [
        'email' => $email,
        'permissions' => [['event_id' => $event->id, 'permission' => $permission->value]],
    ];
}

it('affiche la page Partage d\'événements au propriétaire', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get('/settings/event-sharing');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/EventSharing')
        ->has('collaborators', 0)
        ->has('events', 1)
        ->where('events.0.id', $event->id)
        ->has('permissionOptions', 3));
});

it('refuse le partage d\'événements à un rôle sans inviteMembers', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $this->actingAs($editor)->get('/settings/event-sharing')->assertForbidden();
});

it('invite un collaborateur sur un événement et lui envoie le lien par e-mail', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::CheckIn, ' Equipe@Example.test '));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($organization);
    $collaborator = Collaborator::query()->sole();
    expect($collaborator->email)->toBe('equipe@example.test');
    expect($collaborator->accepted_at)->toBeNull();
    expect($collaborator->eventPermissions()->sole()->permission)->toBe(CollaboratorPermission::CheckIn);
    expect(AuditLog::query()->where('action', 'collaborator.invited')->exists())->toBeTrue();

    Mail::assertQueued(CollaboratorInvitationMail::class, function (CollaboratorInvitationMail $mail) use ($organization, $collaborator, $event): bool {
        $token = Str::afterLast($mail->invitationUrl, '/');

        return $mail->hasTo('equipe@example.test')
            && str_contains($mail->invitationUrl, "/invitations/{$organization->slug}/")
            && Collaborator::hashToken($token) === $collaborator->invitation_token_hash
            && $mail->events === [['title' => $event->title, 'permission' => 'Lecture seule + check-in']];
    });
});

it('exige au moins un événement avec un accès', function (): void {
    Mail::fake();
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::None));

    $response->assertSessionHasErrors('permissions');
    Mail::assertNothingQueued();
});

it('refuse de partager l\'événement d\'une autre organisation', function (): void {
    Mail::fake();
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    ['event' => $foreignEvent] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($foreignEvent, CollaboratorPermission::Administrator));

    $response->assertSessionHasErrors('permissions.0.event_id');
    Mail::assertNothingQueued();
});

it('refuse d\'inviter une personne déjà membre de l\'organisation', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $member = User::factory()->create(['email' => 'membre@example.test']);
    Membership::factory()->for($organization)->for($member)->create(['role' => MembershipRole::Editor]);

    $response = $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::CheckIn, 'membre@example.test'));

    $response->assertSessionHasErrors('email');
});

it('refuse une adresse déjà invitée', function (): void {
    Mail::fake();
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::CheckIn));
    $response = $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::Administrator));

    $response->assertSessionHasErrors('email');
    Mail::assertQueuedCount(1);
});

it('modifie les permissions et repasse un événement à « Aucun accès » sans supprimer de ligne', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $secondEvent = Event::factory()->for($organization)->published()->create();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::CheckIn));
    app(CurrentOrganization::class)->set($organization);
    $collaborator = Collaborator::query()->sole();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->patch("/settings/event-sharing/{$collaborator->id}", [
        'permissions' => [
            ['event_id' => $event->id, 'permission' => 'none'],
            ['event_id' => $secondEvent->id, 'permission' => 'administrator'],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    app(CurrentOrganization::class)->set($organization);
    $permissions = CollaboratorEventPermission::query()->pluck('permission', 'event_id');
    expect($permissions->get($event->id))->toBe(CollaboratorPermission::None);
    expect($permissions->get($secondEvent->id))->toBe(CollaboratorPermission::Administrator);
    expect(AuditLog::query()->where('action', 'collaborator.permissions_updated')->exists())->toBeTrue();
});

it('renvoie l\'invitation avec un nouveau jeton', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->post('/settings/event-sharing', collaboratorPayload($event, CollaboratorPermission::CheckIn));
    app(CurrentOrganization::class)->set($organization);
    $collaborator = Collaborator::query()->sole();
    $firstHash = $collaborator->invitation_token_hash;
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->post("/settings/event-sharing/{$collaborator->id}/resend")->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    expect($collaborator->fresh()->invitation_token_hash)->not->toBe($firstHash);
    Mail::assertQueuedCount(2);
});

it('retire un collaborateur avec son adhésion', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $user = makeEventCollaborator($organization, $event, CollaboratorPermission::Administrator);
    app(CurrentOrganization::class)->set($organization);
    $collaborator = Collaborator::query()->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->delete("/settings/event-sharing/{$collaborator->id}")->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    expect(Collaborator::query()->count())->toBe(0);
    expect(Collaborator::withTrashed()->sole()->deleted_at)->not->toBeNull();
    expect(Membership::query()->where('user_id', $user->id)->exists())->toBeFalse();
    expect(AuditLog::query()->where('action', 'collaborator.removed')->exists())->toBeTrue();
});

it('ne laisse pas modifier le collaborateur d\'une autre organisation', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    ['organization' => $otherOrganization, 'event' => $otherEvent] = makeCheckInEvent(MembershipRole::Owner);
    makeEventCollaborator($otherOrganization, $otherEvent, CollaboratorPermission::CheckIn);
    app(CurrentOrganization::class)->set($otherOrganization);
    $foreignCollaborator = Collaborator::query()->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->delete("/settings/event-sharing/{$foreignCollaborator->id}")->assertNotFound();
});
