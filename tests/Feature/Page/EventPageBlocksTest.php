<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationImage;
use App\Domain\Page\Models\Page;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('compose la page avec des blocs, dans l\'ordre choisi', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = memberOfOrganization($organization, MembershipRole::Admin);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'meta_description' => 'Le gala annuel de notre association.',
        'blocks' => [
            ['type' => 'text', 'title' => 'Le mot du président', 'body' => 'Nous vous attendons nombreux.'],
            ['type' => 'faq', 'title' => null, 'items' => [['question' => 'Y a-t-il un parking ?', 'answer' => 'Oui, à côté de la salle.']]],
            ['type' => 'program', 'items' => [
                ['time' => '18h00', 'title' => 'Accueil', 'description' => null],
                // Ligne entièrement vide : elle ne part pas sur la page.
                ['time' => '', 'title' => '', 'description' => ''],
            ]],
            ['type' => 'countdown', 'title' => 'Plus que'],
        ],
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $page = Page::query()->where('event_id', $event->id)->sole();
    expect(array_column($page->blocks, 'type'))->toBe(['text', 'faq', 'program', 'countdown'])
        ->and($page->blocks[2]['items'])->toHaveCount(1)
        // Le programme et la FAQ restent lisibles hors des blocs (exports).
        ->and($page->program_items)->toHaveCount(1)
        ->and($page->faq_items)->toHaveCount(1);
    app(CurrentOrganization::class)->clear();

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();
    expect($html)->toContain('Le mot du président')
        ->and($html)->toContain('Y a-t-il un parking ?')
        ->and($html)->toContain('Plus que');
    // L'ordre choisi est celui de la page.
    expect(strpos($html, 'Le mot du président'))->toBeLessThan(strpos($html, 'Y a-t-il un parking ?'));
});

it('garde la mise en page d\'origine tant que l\'organisateur n\'a rien composé', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    app(CurrentOrganization::class)->set($organization);
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'program_items' => [['time' => '18h', 'title' => 'Accueil', 'description' => null]],
        'faq_items' => [['question' => 'Parking ?', 'answer' => 'Oui.']],
        'blocks' => null,
    ]);
    app(CurrentOrganization::class)->clear();

    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertSee('Accueil')
        ->assertSee('Parking ?');
});

it('envoie l\'image d\'un bloc dans la bibliothèque de l\'organisation', function (): void {
    Storage::fake('public');
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = memberOfOrganization($organization, MembershipRole::Admin);

    $response = $this->actingAs($admin)
        ->post("/events/{$event->id}/page/images", ['image' => UploadedFile::fake()->image('salle.jpg', 2400, 1200)])
        ->assertOk();

    $path = $response->json('path');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'blocks' => [['type' => 'image', 'path' => $path, 'alt' => 'La salle']],
    ])->assertOk();

    $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->assertSee('alt="La salle"', false);

    // Une image posée sur une page ne peut plus être supprimée de la bibliothèque.
    app(CurrentOrganization::class)->set($organization);
    $image = OrganizationImage::query()->where('path', $path)->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->deleteJson("/media-library/{$image->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.image.0', fn (string $message): bool => str_contains($message, 'page'));
});

it('réserve la composition de la page aux membres qui peuvent modifier l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $viewer = memberOfOrganization($organization, MembershipRole::Viewer);

    $this->actingAs($viewer)->get("/events/{$event->id}/page")->assertForbidden();
    $this->actingAs($viewer)->patchJson("/events/{$event->id}/page", ['blocks' => []])->assertForbidden();
});

it('refuse un bloc inconnu ou trop long', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'blocks' => [['type' => 'text', 'body' => str_repeat('a', 5001)]],
    ])->assertStatus(422);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'blocks' => [['type' => 'piratage', 'body' => 'a']],
    ])->assertStatus(422);
});

/**
 * Un membre créé dans l'organisation de l'événement : deux adhésions
 * rendraient l'organisation courante ambiguë.
 */
function memberOfOrganization(Organization $organization, MembershipRole $role): User
{
    app(CurrentOrganization::class)->set($organization);
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);
    app(CurrentOrganization::class)->clear();

    return $user;
}
