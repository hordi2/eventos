<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\Tag;
use App\Domain\Organization\Models\MembershipRole;

it('crée un tag', function (): void {
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post(route('tags.store'), ['name' => 'VIP', 'color' => '#1a6e42']);
    $response->assertRedirect(route('tags.index'));

    $tag = Tag::query()->firstOrFail();
    expect($tag->name)->toBe('VIP');
    expect($tag->color)->toBe('#1a6e42');
});

it('refuse une couleur qui n\'est pas un hexadécimal valide', function (): void {
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $this->actingAs($admin)->post(route('tags.store'), ['name' => 'VIP', 'color' => 'vert'])
        ->assertSessionHasErrors('color');
});

it('modifie et supprime un tag', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $tag = Tag::factory()->for($organization)->create(['name' => 'Ancien nom']);

    $this->actingAs($admin)->patch(route('tags.update', $tag), ['name' => 'Nouveau nom', 'color' => '#c0392b'])
        ->assertRedirect(route('tags.index'));
    expect($tag->fresh()->name)->toBe('Nouveau nom');

    $this->actingAs($admin)->delete(route('tags.destroy', $tag))->assertRedirect(route('tags.index'));
    expect(Tag::query()->find($tag->id))->toBeNull();
    expect(Tag::withTrashed()->find($tag->id))->not->toBeNull();
});

it('affiche le nombre de contacts par tag', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $tag = Tag::factory()->for($organization)->create();
    $contacts = Contact::factory()->for($organization)->count(2)->create();
    $tag->contacts()->attach($contacts->pluck('id'), ['organization_id' => $organization->id]);

    $this->actingAs($admin)->get(route('tags.index'))
        ->assertInertia(fn ($page) => $page->where('tags.0.contacts_count', 2));
});

it('refuse la création d\'un tag à un rôle sans capacité updateGuests', function (): void {
    [, $viewer] = organizationWithContactRole(MembershipRole::Viewer);

    $this->actingAs($viewer)->post(route('tags.store'), ['name' => 'VIP', 'color' => '#1a6e42'])
        ->assertForbidden();
});
