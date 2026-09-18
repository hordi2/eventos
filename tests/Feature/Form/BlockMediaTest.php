<?php

declare(strict_types=1);

use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use App\Support\Images\ResizeUploadedImage;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('redimensionne l\'image d\'un bloc à l\'envoi et rend son chemin et son adresse', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $response = $this->actingAs($admin)
        ->post("/forms/{$form->id}/block-image", ['image' => UploadedFile::fake()->image('affiche.jpg', 3000, 2000)])
        ->assertOk()
        ->assertJsonStructure(['path', 'url', 'width', 'height']);

    expect($response->json('width'))->toBe(ResizeUploadedImage::MAX_WIDTH);
    expect($response->json('path'))->toStartWith(OrganizationImage::DIRECTORY."/{$form->organization_id}/");
    Storage::disk('public')->assertExists((string) $response->json('path'));
});

it('refuse un fichier qui n\'est pas une image, et interdit l\'envoi à un membre en lecture seule', function (): void {
    Storage::fake('public');
    [$organization, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)
        ->postJson("/forms/{$form->id}/block-image", ['image' => UploadedFile::fake()->create('notice.pdf', 40, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)
        ->post("/forms/{$form->id}/block-image", ['image' => UploadedFile::fake()->image('affiche.jpg')])
        ->assertForbidden();
});

it('enregistre l\'image et la vidéo d\'un bloc, et refuse un lien qui n\'est ni YouTube ni Vimeo', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();
    $path = (string) $this->actingAs($admin)
        ->post("/forms/{$form->id}/block-image", ['image' => UploadedFile::fake()->image('affiche.jpg', 2000, 1000)])
        ->json('path');

    $block = fn (array $config): array => ['key' => 'mot', 'type' => 'informational_text', 'label' => 'Un mot des hôtes', 'config' => $config];

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [$block(['show_if' => 'always', 'image_path' => $path, 'image_alt' => 'La salle de réception', 'image_width' => 1600, 'image_height' => 800, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])],
    ])->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->latestVersion()->fields->first()->config)
        ->toMatchArray(['image_path' => $path, 'image_alt' => 'La salle de réception', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

    // L'adresse publique de l'image est recalculée pour le constructeur, jamais enregistrée.
    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('form.fields.0.config.image_url', Storage::disk('public')->url($path)));

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [$block(['video_url' => 'https://exemple.test/ma-video.mp4']), $block(['image_path' => '../../.env'])],
    ])->assertSessionHasErrors(['fields.0.config.video_url', 'fields.1.config.image_path']);
});

it('montre l\'image sur la page invité et ne charge la vidéo qu\'au clic', function (): void {
    Storage::fake('public');
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    $path = (string) $this->actingAs($admin)->post("/forms/{$form->id}/block-image", ['image' => UploadedFile::fake()->image('salle.jpg', 2000, 1000)])->json('path');

    app(ReviseForm::class)->handle($form, $admin, [[
        'key' => 'mot',
        'type' => 'informational_text',
        'label' => 'Rendez-vous dans la grande salle',
        'config' => ['show_if' => 'always', 'image_path' => $path, 'image_alt' => 'La grande salle', 'image_width' => 1600, 'image_height' => 800, 'video_url' => 'https://vimeo.com/123456789'],
    ]]);
    app(PublishFormVersion::class)->handle($form->fresh(), $admin);
    app(CurrentOrganization::class)->clear();

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com']);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('Rendez-vous dans la grande salle')
        ->assertSee(Storage::disk('public')->url($path), false)
        ->assertSee('alt="La grande salle"', false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('Lire la vidéo')
        ->assertSee('https://player.vimeo.com/video/123456789?autoplay=1', false)
        // Le lecteur vit dans un template Alpine : rien n'est chargé avant le clic.
        ->assertSee('<template x-if="playing">', false);
});
