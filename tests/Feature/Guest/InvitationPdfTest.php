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
use App\Domain\Page\Models\Page;
use App\Models\User;
use App\Support\Invitation\BuildInvitationPdf;
use App\Support\MultiTenancy\CurrentOrganization;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Événement publié dont la page porte un programme, et un invité de la
 * liste avec son lien personnel.
 *
 * @return array{organization: Organization, event: Event, invitee: EventInvitee, base: string}
 */
function eventWithInvitationPdf(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Wedding]);

    app(CurrentOrganization::class)->set($organization);
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'blocks' => [[
            'id' => 'a',
            'type' => 'program',
            'title' => null,
            'items' => [['time' => '19 h 00', 'title' => 'Bénédiction nuptiale', 'description' => 'Grande salle']],
        ]],
    ]);
    $contact = Contact::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Awa', 'last_name' => 'Diallo']);
    $invitee = EventInvitee::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'contact_id' => $contact->id,
    ]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

it('livre le faire-part en PDF depuis le lien personnel de l\'invité', function (): void {
    ['invitee' => $invitee, 'base' => $base] = eventWithInvitationPdf();

    $response = $this->get("{$base}/invitation/{$invitee->invitation_token}/faire-part.pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('.pdf');
    // Un PDF, et pas une page d'erreur déguisée.
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

it('refuse un jeton d\'invitation inventé', function (): void {
    ['base' => $base] = eventWithInvitationPdf();

    $this->get("{$base}/invitation/jeton-invente/faire-part.pdf")->assertNotFound();
});

it('porte le nom de l\'invité, le programme et son lien personnel', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee] = eventWithInvitationPdf();

    app(CurrentOrganization::class)->set($organization);
    $data = app(BuildInvitationPdf::class)->data($event->fresh(), $invitee);
    app(CurrentOrganization::class)->clear();

    expect($data->guestName)->toBe('Awa Diallo')
        ->and($data->eyebrow)->toBe('Vous êtes invité')
        ->and($data->rsvpUrl)->toContain($invitee->invitation_token)
        // Le faire-part suit la page : un feuillet par bloc composé.
        ->and($data->blocks)->toHaveCount(1)
        ->and($data->blocks[0]['type'])->toBe('program')
        ->and($data->blocks[0]['items'][0]['title'])->toBe('Bénédiction nuptiale')
        // Aucun code d'entrée tant que l'invité n'est pas inscrit.
        ->and($data->entryQr)->toBeNull()
        ->and($data->rsvpQr)->toStartWith('data:image/png;base64,');
});

it('ajoute le code d\'entrée au faire-part d\'un inscrit', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithInvitationPdf();
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("{$base}/{$token}/identite", ['email' => 'awa@example.com', 'first_name' => 'Awa', 'last_name' => 'Diallo', 'phone' => '+243970000001']);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->whereNull('parent_registration_id')->sole();
    $data = app(BuildInvitationPdf::class)->data($event->fresh(), null, $registration);
    app(CurrentOrganization::class)->clear();

    expect($data->entryQr)->toStartWith('data:image/png;base64,')
        ->and($data->guestName)->toBe('Awa Diallo');

    // Le lien de téléchargement est proposé sur la confirmation.
    $this->get("{$base}/{$token}/confirmation")->assertOk()->assertSee('Télécharger mon invitation (PDF)');
});

it('refuse un lien de faire-part dont la signature a été touchée', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithInvitationPdf();

    $this->get("/r/{$organization->slug}/{$event->slug}/inscriptions/1/faire-part.pdf")->assertForbidden();
});

it('laisse l\'organisateur relire le faire-part avant de l\'envoyer', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithInvitationPdf();

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($admin)->get("/events/{$event->id}/faire-part.pdf")->assertOk();
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    $this->actingAs($viewer)->get("/events/{$event->id}/faire-part.pdf")->assertForbidden();
});

it('imprime un feuillet par bloc, avec son fond, et écarte ce qui n\'a pas de sens sur papier', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee] = eventWithInvitationPdf();

    app(CurrentOrganization::class)->set($organization);
    Page::query()->where('event_id', $event->id)->sole()->update(['blocks' => [
        ['id' => 'a', 'type' => 'countdown', 'title' => null],
        ['id' => 'b', 'type' => 'guest_book', 'title' => null],
        ['id' => 'c', 'type' => 'details', 'title' => 'Bon à savoir', 'items' => [
            ['title' => 'Thème', 'time' => 'Chic et élégant', 'description' => null],
        ], 'background' => 'organization-images/1/fond.jpg', 'backgroundOverlay' => 70, 'textTone' => 'light'],
    ]]);
    $data = app(BuildInvitationPdf::class)->data($event->fresh(), $invitee);
    app(CurrentOrganization::class)->clear();

    // Le décompte et le livre d'or ne s'impriment pas.
    expect($data->blocks)->toHaveCount(1)
        ->and($data->blocks[0]['type'])->toBe('details')
        ->and($data->blocks[0]['onDark'])->toBeTrue()
        ->and($data->blocks[0]['overlay'])->toBe(0.7)
        // L'image du fond n'existe pas sur le disque : la mise en page tient sans elle.
        ->and($data->blocks[0]['backgroundImage'])->toBeNull();
});
