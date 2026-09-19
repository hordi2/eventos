<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationImage;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        ->where('form.has_published_version', false)
        ->where('isFreePlan', true)
        ->has('fonts', 5)
        ->where('fonts.0.stack', fn (string $stack): bool => str_contains($stack, 'Plus Jakarta Sans'))
        ->has('event.phoneRequired')
        ->where('fieldTypes', fn ($types): bool => collect($types)->firstWhere('value', 'quantity')['premium'] === true));
});

it('ignore un chemin ou une adresse d\'image glissés dans les réglages du thème', function (): void {
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        'settings' => ['theme' => ['logo_path' => '../../.env', 'logo_url' => 'https://exemple.test/logo.png', 'accent_color' => '#123456']],
    ])->assertRedirect(route('forms.edit', $form));

    $theme = $form->fresh()->settings['theme'];
    expect($theme['logo_path'])->toBeNull();
    expect($theme)->not->toHaveKey('logo_url');
    expect($theme['accent_color'])->toBe('#123456');
});

it('refuse une logique conditionnelle circulaire sans toucher aux questions enregistrées', function (): void {
    [, $admin, $form] = formWithBuilderSettings();
    $dependsOn = fn (string $source): array => ['combinator' => 'and', 'conditions' => [['field_key' => $source, 'operator' => 'is_not_empty', 'value' => '']]];

    $this->actingAs($admin)->from("/forms/{$form->id}/edit")->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'a', 'type' => 'short_text', 'label' => 'Question A'],
            ['key' => 'b', 'type' => 'short_text', 'label' => 'Question B'],
        ],
        'rules' => [
            ['target_field_key' => 'a', 'action' => 'show', 'condition_group' => $dependsOn('b')],
            ['target_field_key' => 'b', 'action' => 'show', 'condition_group' => $dependsOn('a')],
        ],
    ])->assertRedirect("/forms/{$form->id}/edit")->assertSessionHasErrors('rules');

    expect($form->fresh()->latestVersion()->fields->pluck('key')->all())->toBe(['nom']);
});

it('ne garde dans le bloc « Événements secondaires » que les sessions de l\'événement, avec leur vrai titre', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();
    $session = Event::factory()->for($organization)->create(['parent_event_id' => $form->event_id, 'title' => 'Dîner de gala']);
    $otherEvent = Event::factory()->for($organization)->create(['title' => 'Autre événement']);

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [[
            'key' => 'sessions',
            'type' => 'sub_events',
            'label' => 'Vos sessions',
            'config' => ['sub_events' => [
                ['id' => $session->id, 'title' => 'Titre modifié dans le navigateur'],
                ['id' => $otherEvent->id, 'title' => 'Autre événement'],
            ]],
        ]],
    ])->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->latestVersion()->fields->first()->config['sub_events'])->toBe([['id' => $session->id, 'title' => 'Dîner de gala']]);

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->has('subEvents', 1)
        ->where('subEvents.0.title', 'Dîner de gala'));
});

it('enregistre un bloc « Don » et refuse une devise inconnue ou un don sans montant possible', function (): void {
    [, $admin, $form] = formWithBuilderSettings();
    $donation = fn (string $key, array $config): array => ['key' => $key, 'type' => 'donation', 'label' => 'Un don ?', 'config' => $config];

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [$donation('don', ['currency' => 'CDF', 'amounts' => [1000000, 2500000], 'allow_custom' => true, 'cause' => 'Bibliothèque'])],
    ])->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->latestVersion()->fields->first()->config)
        ->toMatchArray(['currency' => 'CDF', 'amounts' => [1000000, 2500000], 'cause' => 'Bibliothèque']);

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('donationCurrencies.0.code', 'XAF')
        ->has('defaultDonationCurrency'));

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [$donation('don', ['currency' => 'GBP']), $donation('don_2', ['amounts' => [], 'allow_custom' => false])],
    ])->assertSessionHasErrors(['fields.0.config.currency', 'fields.1.config.amounts']);
});

it('enregistre un bloc « Fichier joint » et refuse un format inconnu, aucun format ou une taille trop grande', function (): void {
    [, $admin, $form] = formWithBuilderSettings();
    $file = fn (string $key, array $config): array => ['key' => $key, 'type' => 'file_upload', 'label' => 'Votre justificatif', 'config' => $config];

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [$file('justificatif', ['file_types' => ['pdf', 'images'], 'max_size_mb' => 5])],
    ])->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->latestVersion()->fields->first()->config)->toMatchArray(['file_types' => ['pdf', 'images'], 'max_size_mb' => 5]);

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->has('fileUploadTypes', 3)
        ->where('maxFileSizeMb', 10)
        ->where('fieldTypes', fn ($types): bool => collect($types)->firstWhere('value', 'file_upload')['premium'] === true));

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [$file('a', ['file_types' => ['exe']]), $file('b', ['file_types' => []]), $file('c', ['max_size_mb' => 50])],
    ])->assertSessionHasErrors(['fields.0.config.file_types.0', 'fields.1.config.file_types', 'fields.2.config.max_size_mb']);
});

it('enregistre le nombre maximal d\'accompagnants et une question posée à chaque personne', function (): void {
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'menu', 'type' => 'short_text', 'label' => 'Menu', 'config' => ['ask_scope' => 'each_attendee']]],
        'settings' => ['rsvp' => ['max_companions' => 3]],
    ])->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->settings['rsvp']['max_companions'])->toBe(3);
    expect($form->fresh()->latestVersion()->fields->first()->config['ask_scope'])->toBe('each_attendee');

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => '_companions', 'type' => 'short_text', 'label' => 'Menu', 'config' => ['ask_scope' => 'tout le monde']]],
        'settings' => ['rsvp' => ['max_companions' => 50]],
    ])->assertSessionHasErrors(['fields.0.key', 'fields.0.config.ask_scope', 'settings.rsvp.max_companions']);
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

it('téléverse puis retire le logo du thème, en gardant le fichier dans la bibliothèque', function (): void {
    Storage::fake('public');
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)
        ->post("/forms/{$form->id}/theme/logo", ['image' => UploadedFile::fake()->image('logo.png', 400, 120)])
        ->assertOk()
        ->assertJsonStructure(['url']);

    $path = $form->fresh()->settings['theme']['logo_path'];
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->delete("/forms/{$form->id}/theme/logo")->assertNoContent();

    // Retirer ne fait que détacher : l'image peut servir ailleurs et reste
    // dans « Mes images », d'où elle se supprime pour de bon.
    Storage::disk('public')->assertExists($path);
    expect($form->fresh()->settings['theme']['logo_path'])->toBeNull();
    expect(OrganizationImage::query()->where('path', $path)->exists())->toBeTrue();
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

it('montre les textes par défaut quand un titre ou un message est laissé vide', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    // Un champ vidé dans le constructeur arrive vide (null) : c'est ce qu'enregistre la sauvegarde.
    app(CurrentOrganization::class)->set($organization);
    Form::query()->where('event_id', $event->id)->sole()->update(['settings' => [
        'confirmation' => ['title' => null, 'message' => null],
        'welcome' => ['enabled' => true, 'title' => null, 'message' => null, 'button_label' => null],
    ]]);
    app(CurrentOrganization::class)->clear();

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;

    $this->get("{$base}/{$token}/accueil")->assertOk()->assertSee('Commencer');

    $this->post("{$base}/{$token}/identite", ['email' => 'awa@example.com', 'first_name' => 'Awa']);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    $this->get("{$base}/{$token}/confirmation")
        ->assertOk()
        ->assertSee('Inscription confirmée')
        ->assertSee("Merci, votre inscription à {$event->title} est enregistrée.", false);
});
