<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\EventTemplate;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Templates\CreateEventFromTemplate;
use App\Support\Templates\PublishEventTemplate;
use Carbon\CarbonImmutable;

/**
 * Bibliothèque de modèles communautaire (D11).
 *
 * @return array{0: Organization, 1: User, 2: Event}
 */
function templateEvent(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(
        [['key' => 'entreprise', 'type' => 'short_text', 'label' => 'Votre entreprise', 'is_required' => true]],
        ['type' => EventType::Conference, 'requires_approval' => true],
    );

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'blocks' => [
            ['id' => 't', 'type' => 'text', 'title' => 'Le mot du président', 'body' => 'Bienvenue.'],
            ['id' => 'i', 'type' => 'image', 'title' => null, 'path' => 'organization-images/1/photo.jpg', 'alt' => null],
        ],
    ]);
    app(CurrentOrganization::class)->clear();

    return [$organization, $admin, $event];
}

it('publie la structure d\'un événement, sans ses données ni ses images', function (): void {
    [$organization, $admin, $event] = templateEvent();

    app(CurrentOrganization::class)->set($organization);
    $template = app(PublishEventTemplate::class)->handle($event, $admin, 'Conférence partenaires', 'Une journée, 300 personnes.');
    app(CurrentOrganization::class)->clear();

    expect($template->category)->toBe(EventType::Conference)
        ->and($template->payload['fields'])->toHaveCount(1)
        ->and($template->payload['fields'][0]['label'])->toBe('Votre entreprise')
        ->and($template->payload['settings']['requires_approval'])->toBeTrue()
        ->and($template->payload['blocks'])->toHaveCount(2)
        // Le chemin de l'image ne vaudrait rien ailleurs : il ne part pas.
        ->and($template->payload['blocks'][1])->not->toHaveKey('path');
});

it('monte un événement en brouillon à partir d\'un modèle d\'une autre organisation', function (): void {
    [$publisherOrganization, $publisher, $event] = templateEvent();

    app(CurrentOrganization::class)->set($publisherOrganization);
    $template = app(PublishEventTemplate::class)->handle($event, $publisher, 'Conférence partenaires', null);
    app(CurrentOrganization::class)->clear();

    // Une autre organisation, qui n'a rien à voir avec celle qui a publié.
    $other = Organization::factory()->create();
    app(CurrentOrganization::class)->set($other);
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $other->id, 'role' => MembershipRole::Owner]);

    $created = app(CreateEventFromTemplate::class)->handle(
        $template,
        $other,
        $user,
        'Nos partenaires 2027',
        CarbonImmutable::parse('2027-03-15 09:00'),
    );

    expect($created->organization_id)->toBe($other->id)
        ->and($created->title)->toBe('Nos partenaires 2027')
        // Un modèle donne une structure, pas un calendrier : l'événement naît
        // en brouillon.
        ->and($created->status)->toBe(EventStatus::Draft)
        ->and($created->type)->toBe(EventType::Conference)
        ->and($created->requires_approval)->toBeTrue();

    $page = Page::query()->where('event_id', $created->id)->sole();
    expect($page->blocks)->toHaveCount(2)
        ->and($page->blocks[0]['title'])->toBe('Le mot du président');
    app(CurrentOrganization::class)->clear();

    expect($template->refresh()->uses_count)->toBe(1);
});

it('montre la bibliothèque aux autres organisations, et cache ce qui est retiré', function (): void {
    [$publisherOrganization, $publisher, $event] = templateEvent();

    app(CurrentOrganization::class)->set($publisherOrganization);
    $template = app(PublishEventTemplate::class)->handle($event, $publisher, 'Conférence partenaires', null);
    app(CurrentOrganization::class)->clear();

    $other = Organization::factory()->create();
    app(CurrentOrganization::class)->set($other);
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $other->id, 'role' => MembershipRole::Owner]);
    app(CurrentOrganization::class)->clear();

    $session = ['current_organization_id' => $other->id];

    $this->actingAs($user)->withSession($session)->get('/modeles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Templates/Index')
            ->where('templates.0.name', 'Conférence partenaires')
            // Ce n'est pas le sien : il ne peut pas le retirer.
            ->where('templates.0.mine', false));

    // Le retrait appartient à qui a publié.
    app(CurrentOrganization::class)->set($publisherOrganization);
    $template->update(['is_published' => false]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($user)->withSession($session)->get('/modeles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('templates', 0));
});

it('ne laisse pas une organisation retirer le modèle d\'une autre', function (): void {
    [$publisherOrganization, $publisher, $event] = templateEvent();

    app(CurrentOrganization::class)->set($publisherOrganization);
    $template = app(PublishEventTemplate::class)->handle($event, $publisher, 'Conférence partenaires', null);
    app(CurrentOrganization::class)->clear();

    $other = Organization::factory()->create();
    app(CurrentOrganization::class)->set($other);
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $other->id, 'role' => MembershipRole::Owner]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $other->id])
        ->patch("/modeles/{$template->id}", ['is_published' => false])
        ->assertNotFound();

    expect(EventTemplate::query()->whereKey($template->id)->sole()->is_published)->toBeTrue();
});
