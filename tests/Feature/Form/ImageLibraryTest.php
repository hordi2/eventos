<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('ne montre à un organisateur que les images de son organisation', function (): void {
    Storage::fake('public');
    [$organization, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertOk();

    [$autreOrganisation, $autreAdmin] = organizationWithContactRole(MembershipRole::Admin);
    OrganizationImage::factory()->create(['organization_id' => $autreOrganisation->id, 'original_name' => 'affiche-voisine.jpg']);

    $mienne = $this->actingAs($admin)->getJson('/media-library')->assertOk();
    expect($mienne->json('total'))->toBe(1);
    expect($mienne->json('images.0.name'))->toBe('logo.png');
    expect($mienne->json('images.0.path'))->toStartWith(OrganizationImage::DIRECTORY."/{$organization->id}/");

    $voisine = $this->actingAs($autreAdmin)->getJson('/media-library')->assertOk();
    expect($voisine->json('total'))->toBe(1);
    expect($voisine->json('images.0.name'))->toBe('affiche-voisine.jpg');
});

it('refuse de supprimer une image encore utilisée, puis l\'accepte une fois détachée', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertOk();
    $path = (string) $form->fresh()->settings['theme']['logo_path'];
    $image = OrganizationImage::query()->where('path', $path)->firstOrFail();

    $refus = $this->actingAs($admin)->deleteJson("/media-library/{$image->id}")->assertUnprocessable();
    expect($refus->json('errors.image.0'))->toContain('logo du formulaire « Inscription »');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->delete("/forms/{$form->id}/theme/logo")->assertNoContent();
    $this->actingAs($admin)->deleteJson("/media-library/{$image->id}")->assertNoContent();

    Storage::disk('public')->assertMissing($path);
    expect(OrganizationImage::query()->find($image->id))->toBeNull();
});

it('refuse de supprimer une image utilisée par un bloc, même dans une ancienne version', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $path = (string) $this->actingAs($admin)
        ->post("/forms/{$form->id}/block-image", ['image' => UploadedFile::fake()->image('salle.jpg', 1200, 600)])
        ->json('path');

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'mot', 'type' => 'informational_text', 'label' => 'Un mot des hôtes', 'config' => ['show_if' => 'always', 'image_path' => $path]]],
    ])->assertRedirect(route('forms.edit', $form));

    $image = OrganizationImage::query()->where('path', $path)->firstOrFail();
    $refus = $this->actingAs($admin)->deleteJson("/media-library/{$image->id}")->assertUnprocessable();

    expect($refus->json('errors.image.0'))->toContain('bloc « Un mot des hôtes »');
});

it('reprend une image de la bibliothèque pour le fond du thème sans la renvoyer', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertOk();
    $image = OrganizationImage::query()->firstOrFail();

    $this->actingAs($admin)->postJson("/forms/{$form->id}/theme/background", ['image_id' => $image->id])->assertOk();

    $theme = $form->fresh()->settings['theme'];
    expect($theme['background_image_path'])->toBe($image->path);
    expect($theme['logo_path'])->toBe($image->path);
    // Aucun second fichier : la bibliothèque prête l'image, elle ne la copie pas.
    expect(OrganizationImage::query()->count())->toBe(1);

    $this->actingAs($admin)->postJson("/forms/{$form->id}/theme/background", ['image_id' => $image->id + 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image_id');
});

it('refuse dans un bloc l\'image d\'une autre organisation et interdit la bibliothèque en lecture seule', function (): void {
    Storage::fake('public');
    [$organization, $admin, $form] = formWithBuilderSettings();

    [$autreOrganisation] = organizationWithContactRole(MembershipRole::Admin);
    $imageVoisine = OrganizationImage::factory()->create(['organization_id' => $autreOrganisation->id]);
    app(CurrentOrganization::class)->set($organization);

    $this->actingAs($admin)->from("/forms/{$form->id}/edit")->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'mot', 'type' => 'informational_text', 'label' => 'Un mot', 'config' => ['image_path' => $imageVoisine->path]]],
    ])->assertSessionHasErrors('fields.0.config.image_path');

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->getJson('/media-library')->assertForbidden();
});

it('supprime une image sans compter sur une organisation déjà posée avant la requête', function (): void {
    Storage::fake('public');
    [$organization, $admin] = formWithBuilderSettings();
    $image = OrganizationImage::factory()->create(['organization_id' => $organization->id]);
    Storage::disk('public')->put($image->path, 'contenu');

    // En vrai, chaque requête part sans organisation courante : c'est le
    // middleware qui la pose. Laisser celle de la préparation masquerait
    // une recherche faite trop tôt (liaison implicite de la route).
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->deleteJson("/media-library/{$image->id}")->assertNoContent();

    Storage::disk('public')->assertMissing($image->path);
});
