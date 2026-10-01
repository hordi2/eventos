<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Support\MultiTenancy\CurrentOrganization;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Événement publié dont la page porte les trois blocs d'invitation : le
 * code d'entrée, la galerie et les boutons de réponse.
 *
 * @return array{organization: Organization, event: Event, invitee: EventInvitee, base: string}
 */
function eventWithInvitationBlocks(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    app(CurrentOrganization::class)->set($organization);
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'blocks' => [
            ['id' => 'q', 'type' => 'entry_qr', 'title' => null, 'body' => null],
            ['id' => 'g', 'type' => 'gallery', 'title' => 'En images', 'items' => [
                ['path' => 'organization-images/1/photo.jpg', 'description' => 'La salle'],
            ]],
            ['id' => 'r', 'type' => 'rsvp', 'title' => null, 'body' => 'Merci de nous le dire.'],
        ],
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

it('montre les trois réponses possibles, et la galerie', function (): void {
    ['base' => $base] = eventWithInvitationBlocks();

    $this->get($base)
        ->assertOk()
        ->assertSee('Confirmez votre présence')
        ->assertSee('Je confirme ma présence')
        ->assertSee('Je vais confirmer plus tard')
        // Le formulaire n'accepte pas de refus : le bouton ne se montre pas,
        // il mènerait à une inscription.
        ->assertDontSee('Je ne pourrai pas répondre présent')
        ->assertSee('En images')
        ->assertSee('organization-images/1/photo.jpg', false);
});

it('ne montre le code d\'entrée qu\'à l\'invité venu par son lien personnel', function (): void {
    ['invitee' => $invitee, 'base' => $base] = eventWithInvitationBlocks();

    // Visiteur anonyme : aucun code, mais on lui dit où le trouver.
    $this->get($base)
        ->assertOk()
        ->assertSee('Votre entrée')
        ->assertSee('Votre code personnel apparaît ici')
        ->assertDontSee('/qr.png', false);

    // Le lien personnel ouvre l'invitation : le code et le nom s'affichent.
    $this->get("{$base}/invitation/{$invitee->invitation_token}")->assertRedirect($base);

    $this->get($base)
        ->assertOk()
        ->assertSee("invitation/{$invitee->invitation_token}/qr.png", false)
        ->assertSee('Invitation adressée à Awa Diallo');
});

it('propose le refus, et le coche déjà, quand le formulaire l\'accepte', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base] = eventWithInvitationBlocks();

    app(CurrentOrganization::class)->set($organization);
    $form = Form::query()->where('event_id', $event->id)->sole();
    $form->update(['settings' => ['rsvp' => ['decline_enabled' => true]]]);
    app(CurrentOrganization::class)->clear();

    $this->get($base)->assertOk()->assertSee('Je ne pourrai pas répondre présent');

    $target = $this->get("{$base}/commencer?reponse=non")->headers->get('Location');
    expect($target)->toContain('reponse=non');

    // Le formulaire d'identité arrive avec le refus déjà coché.
    $this->get($target)->assertOk()->assertSee('value="0" x-model="attending" checked', false);
});
