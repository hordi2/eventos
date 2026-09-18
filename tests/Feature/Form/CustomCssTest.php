<?php

declare(strict_types=1);

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\PlanTier;
use App\Support\MultiTenancy\CurrentOrganization;

it('enregistre le CSS personnalisé d\'un plan payant et le rend au constructeur', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();
    $organization->forceFill(['plan' => PlanTier::PersonalEssential])->save();

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        'settings' => ['theme' => ['custom_css' => ".carte { border-radius: 2rem; }\n/* soigner les boutons */\n.bouton { letter-spacing: .04em; }"]],
    ])->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->settings['theme']['custom_css'])->toContain('border-radius: 2rem');

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('form.settings.theme.custom_css', fn (string $css): bool => str_contains($css, '.bouton')));
});

it('refuse le CSS personnalisé au plan Gratuit', function (): void {
    [, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->from("/forms/{$form->id}/edit")->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        'settings' => ['theme' => ['custom_css' => '.carte { border-radius: 2rem; }']],
    ])->assertSessionHasErrors('settings.theme.custom_css');

    expect($form->fresh()->settings)->toBeNull();
});

it('refuse un CSS qui ferait charger une feuille ou une adresse extérieure, en le nommant', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();
    $organization->forceFill(['plan' => PlanTier::PersonalEssential])->save();

    $reponse = $this->actingAs($admin)->from("/forms/{$form->id}/edit")->patch("/forms/{$form->id}", [
        'name' => 'Inscription',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        'settings' => ['theme' => ['custom_css' => '@import url("https://exemple.test/tout.css"); .x { background: url(https://pisteur.test/p.gif); }']],
    ])->assertSessionHasErrors('settings.theme.custom_css');

    $message = session('errors')->first('settings.theme.custom_css');
    expect($message)->toContain('@import');
    expect($message)->toContain('url()');
});

it('sert le CSS personnalisé à l\'invité comme feuille de style, et rien quand il n\'y en a pas', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(
        [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        organizationOverrides: ['plan' => PlanTier::PersonalEssential],
    );
    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;

    // Sans CSS : aucune feuille supplémentaire n'est liée.
    $sansCss = $this->get("{$base}/{$token}/identite")->assertOk();
    expect($sansCss->getContent())->not->toContain('theme.css');

    app(CurrentOrganization::class)->set($organization);
    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    $form->update(['settings' => ['theme' => ['custom_css' => '.carte { border-radius: 2rem; }']]]);
    app(CurrentOrganization::class)->clear();

    $page = $this->get("{$base}/{$token}/identite")->assertOk();
    expect($page->getContent())->toContain('theme.css?v=');

    $feuille = $this->get("{$base}/theme.css")->assertOk();
    expect($feuille->headers->get('Content-Type'))->toContain('text/css');
    expect($feuille->getContent())->toContain('border-radius: 2rem');
});

it('ne sert jamais à l\'invité un CSS refusé, même enregistré directement en base', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(
        [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        organizationOverrides: ['plan' => PlanTier::PersonalEssential],
    );

    app(CurrentOrganization::class)->set($organization);
    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    $form->update(['settings' => ['theme' => ['custom_css' => '@import url("https://exemple.test/tout.css");']]]);
    app(CurrentOrganization::class)->clear();

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;

    expect($this->get("{$base}/{$token}/identite")->assertOk()->getContent())->not->toContain('theme.css');
    expect($this->get("{$base}/theme.css")->assertOk()->getContent())->toBe('');
});

it('offre aux pages invité des repères stables à cibler', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']]);

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;

    // Sans ces repères, un CSS personnalisé ne pourrait viser que des classes
    // utilitaires, qui changent à chaque construction des styles.
    $this->get("{$base}/{$token}/identite")
        ->assertSee('itaza-page', false)
        ->assertSee('itaza-progress', false);

    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com']);
    $this->get("{$base}/{$token}/reponses")->assertSee('itaza-field', false);
});
