<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{0: Organization, 1: User, 2: Form}
 */
function formWithBuilderSettings(array $fields = []): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => $fields === [] ? [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']] : $fields,
    ]);

    return [$organization, $admin, $form];
}

it('enregistre les écrans, le thème et le public des questions avec le formulaire', function (): void {
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom', 'config' => ['show_if' => 'always']]],
        'settings' => [
            'welcome' => ['enabled' => true, 'title' => 'Bienvenue'],
            'rsvp' => ['decline_enabled' => true, 'decline_label' => 'Je serai en voyage'],
            'theme' => ['accent_color' => '#0f766e', 'heading_font' => 'georgia'],
        ],
    ])->assertRedirect(route('forms.edit', $form));

    $fresh = $form->fresh();
    expect($fresh->settings['welcome']['title'])->toBe('Bienvenue');
    expect($fresh->settings['welcome']['button_label'])->toBe('Commencer');
    expect($fresh->settings['rsvp']['decline_label'])->toBe('Je serai en voyage');
    expect($fresh->settings['theme']['accent_color'])->toBe('#0f766e');
    expect($fresh->latestVersion()->fields->first()->config['show_if'])->toBe('always');

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('form.settings.theme.heading_font', 'georgia')
        ->where('form.settings.welcome.title', 'Bienvenue')
        ->where('isFreePlan', true)
        ->has('fonts', 5)
        ->where('fieldTypes', fn ($types): bool => collect($types)->firstWhere('value', 'quantity')['premium'] === true));
});

it('refuse une couleur de thème invalide, une police inconnue et un public de question inconnu', function (): void {
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom', 'config' => ['show_if' => 'peut-etre']]],
        'settings' => ['theme' => ['accent_color' => 'red; } body { display:none', 'heading_font' => 'comic-sans']],
    ])->assertSessionHasErrors(['fields.0.config.show_if', 'settings.theme.accent_color', 'settings.theme.heading_font']);

    expect($form->fresh()->settings)->toBeNull();
});

it('refuse de réserver une question à un tag d\'une autre organisation', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();
    $other = Organization::factory()->create();
    app(CurrentOrganization::class)->set($other);
    $foreignTag = Tag::factory()->create(['organization_id' => $other->id]);
    app(CurrentOrganization::class)->set($organization);

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom', 'config' => ['tag_ids' => [$foreignTag->id]]]],
    ])->assertSessionHasErrors('fields.0.config.tag_ids.0');
});

it('refuse de publier au plan Gratuit un formulaire avec une question avancée', function (): void {
    [, $admin, $form] = formWithBuilderSettings([['key' => 'places', 'type' => 'quantity', 'label' => 'Nombre de places']]);

    $this->actingAs($admin)->post("/forms/{$form->id}/publish")
        ->assertRedirect(route('forms.edit', $form))
        ->assertSessionHasErrors('publish');

    expect($form->fresh()->latestVersion()->status)->toBe(FormVersionStatus::Draft);
});

it('publie une question avancée sur un plan payant', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings([['key' => 'places', 'type' => 'quantity', 'label' => 'Nombre de places']]);
    $organization->forceFill(['plan' => PlanTier::PersonalEssential])->save();

    $this->actingAs($admin)->post("/forms/{$form->id}/publish")->assertSessionHasNoErrors();

    expect($form->fresh()->latestVersion()->status)->toBe(FormVersionStatus::Published);
});

it('téléverse puis retire le logo du thème', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)
        ->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png', 400, 120)])
        ->assertOk()
        ->assertJsonStructure(['url']);

    $path = $form->fresh()->settings['theme']['logo_path'];
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->delete("/forms/{$form->id}/theme/logo")->assertNoContent();

    Storage::disk('public')->assertMissing($path);
    expect($form->fresh()->settings['theme']['logo_path'])->toBeNull();
});

it('refuse une image de thème qui n\'est ni PNG ni JPG, et un type d\'image inconnu', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)
        ->postJson("/forms/{$form->id}/theme/background", ['image' => UploadedFile::fake()->create('menu.pdf', 50, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    $this->actingAs($admin)->post("/forms/{$form->id}/theme/banniere", [])->assertNotFound();
});

it('interdit de changer le thème à un membre en lecture seule', function (): void {
    Storage::fake('public');
    [$organization, , $form] = formWithBuilderSettings();
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)
        ->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png')])
        ->assertForbidden();

    expect($form->fresh()->settings)->toBeNull();
});
