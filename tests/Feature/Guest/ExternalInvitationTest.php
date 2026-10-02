<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Invitation\ExternalInvitationLink;
use App\Support\Invitation\ResolveInviteeQr;
use App\Support\MultiTenancy\CurrentOrganization;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Événement publié avec un invité de la liste, et son lien personnel.
 *
 * @return array{organization: Organization, event: Event, invitee: EventInvitee, contact: Contact, base: string}
 */
function eventWithExternalInvitation(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Wedding]);

    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@example.com']);
    $invitee = EventInvitee::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'contact_id' => $contact->id,
    ]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'contact' => $contact, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

it('sert le code QR personnel de l\'invité en image', function (): void {
    ['invitee' => $invitee, 'base' => $base] = eventWithExternalInvitation();

    $response = $this->get("{$base}/invitation/{$invitee->invitation_token}/qr.png")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        // Une vraie image PNG, reconnaissable à sa signature.
        ->and(substr($response->getContent(), 1, 3))->toBe('PNG');
});

it('refuse le code QR d\'un jeton inventé', function (): void {
    ['base' => $base] = eventWithExternalInvitation();

    $this->get("{$base}/invitation/jeton-invente/qr.png")->assertNotFound();
});

it('met l\'adresse de l\'invitation dans le QR, puis son code d\'entrée une fois inscrit', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'base' => $base] = eventWithExternalInvitation();

    app(CurrentOrganization::class)->set($organization);
    $before = app(ResolveInviteeQr::class)->data($event->fresh(), $invitee);
    app(CurrentOrganization::class)->clear();

    expect($before)->toContain($invitee->invitation_token);

    // L'invité répond : son code devient son billet d'entrée.
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("{$base}/{$token}/identite", ['email' => 'awa@example.com', 'first_name' => 'Awa', 'last_name' => 'Diallo', 'phone' => '+243970000001', 'attending' => 1]);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->whereNull('parent_registration_id')->sole()->contact_id)->toBe($invitee->contact_id);
    $after = app(ResolveInviteeQr::class)->data($event->fresh(), $invitee);
    app(CurrentOrganization::class)->clear();

    expect($after)->not->toBe($before)
        ->and($after)->not->toContain('http');
});

it('envoie l\'invité vers le site externe, avec de quoi personnaliser la page', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'base' => $base] = eventWithExternalInvitation();

    app(CurrentOrganization::class)->set($organization);
    $event->update(['external_invitation_url' => 'https://mon-mariage.example/invitation']);
    app(CurrentOrganization::class)->clear();

    $response = $this->get("{$base}/invitation/{$invitee->invitation_token}");
    $target = $response->headers->get('Location');

    expect($target)->toStartWith('https://mon-mariage.example/invitation?')
        ->and($target)->toContain('invitation='.$invitee->invitation_token)
        ->and($target)->toContain(urlencode('/qr.png'))
        // Aucune donnée personnelle dans l'adresse : ni nom, ni e-mail.
        ->and($target)->not->toContain('Awa')
        ->and($target)->not->toContain('awa%40example.com');
});

it('garde les paramètres déjà présents dans l\'adresse du site externe', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee] = eventWithExternalInvitation();

    app(CurrentOrganization::class)->set($organization);
    $event->update(['external_invitation_url' => 'https://mon-mariage.example/?lang=fr']);
    $url = app(ExternalInvitationLink::class)->urlFor($event->fresh(), $invitee);
    app(CurrentOrganization::class)->clear();

    expect($url)->toContain('?lang=fr&')->toContain('invitation=');
});

it('reste sur Itaza quand aucun site externe n\'est indiqué', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'base' => $base] = eventWithExternalInvitation();

    $this->get("{$base}/invitation/{$invitee->invitation_token}")->assertRedirect($base);

    app(CurrentOrganization::class)->set($organization);
    expect(app(ExternalInvitationLink::class)->urlFor($event->fresh(), $invitee))->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('n\'accepte qu\'une adresse complète et chiffrée pour le site externe', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['timezone' => 'UTC']);
    app(CurrentOrganization::class)->clear();

    $payload = [
        'title' => $event->title,
        'start_at' => $event->start_at->toIso8601String(),
        'timezone' => 'UTC',
    ];

    $this->actingAs($admin)->patch("/events/{$event->id}", [...$payload, 'external_invitation_url' => 'mon-mariage.example'])
        ->assertSessionHasErrors('external_invitation_url');

    $this->actingAs($admin)->patch("/events/{$event->id}", [...$payload, 'external_invitation_url' => 'http://mon-mariage.example'])
        ->assertSessionHasErrors('external_invitation_url');

    $this->actingAs($admin)->patch("/events/{$event->id}", [...$payload, 'external_invitation_url' => 'https://mon-mariage.example'])
        ->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($organization);
    expect($event->fresh()->external_invitation_url)->toBe('https://mon-mariage.example');
    app(CurrentOrganization::class)->clear();
});

it('montre l\'adresse du site externe à l\'organisateur', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $event->update(['external_invitation_url' => 'https://mon-mariage.example']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/edit")->assertInertia(fn ($page) => $page
        ->where('event.externalInvitationUrl', 'https://mon-mariage.example'));

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});
