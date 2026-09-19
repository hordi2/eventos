<?php

declare(strict_types=1);

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('pose un bandeau d\'en-tête, réduit à l\'envoi, et le rend au constructeur', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    // Une photo de téléphone de plusieurs Mo passe : elle est réduite.
    $this->actingAs($admin)
        ->post("/forms/{$form->id}/theme/header", ['image' => UploadedFile::fake()->image('salle.jpg', 3200, 1200)->size(4000)])
        ->assertOk();

    $path = (string) $form->fresh()->settings['theme']['header_image_path'];
    expect($path)->toStartWith(OrganizationImage::DIRECTORY.'/');
    expect(OrganizationImage::query()->where('path', $path)->sole()->width)->toBe(1600);

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('form.settings.theme.header_image_url', Storage::disk('public')->url($path)));

    // Utilisé comme bandeau : pas de suppression définitive.
    $image = OrganizationImage::query()->where('path', $path)->sole();
    $this->actingAs($admin)->deleteJson("/media-library/{$image->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.image.0', fn (string $message): bool => str_contains($message, 'bandeau du formulaire'));
});

it('affiche le bandeau en tête de la page invité, le logo posé par-dessus', function (): void {
    Storage::fake('public');
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/forms/{$form->id}/theme/header", ['image' => UploadedFile::fake()->image('salle.jpg', 1600, 600)]);
    $this->actingAs($admin)->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png', 600, 200)]);
    auth()->logout();

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;

    $page = $this->get("{$base}/{$token}/identite")->assertOk()->getContent();
    $theme = $form->fresh()->settings['theme'];
    $header = strpos($page, Storage::disk('public')->url($theme['header_image_path']));
    $logo = strpos($page, Storage::disk('public')->url($theme['logo_path']));

    expect($page)->toContain('class="itaza-header"')->toContain('fetchpriority="high"');
    expect($header)->toBeLessThan($logo);
});
