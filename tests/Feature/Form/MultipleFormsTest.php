<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Registration\PresentEventAnswers;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deuxième formulaire publié d'un événement prêt pour les invités
 * (makeGuestReadyEvent), avec l'administrateur qui l'a créé.
 *
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{0: Form, 1: User}
 */
function addPublishedForm(Organization $organization, Event $event, string $name, array $fields = []): array
{
    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => $name, 'fields' => $fields]);
    app(PublishFormVersion::class)->handle($form, $admin);
    $form = $form->fresh();
    app(CurrentOrganization::class)->clear();

    return [$form, $admin];
}

it('donne à chaque formulaire son lien, le premier répondant au lien de l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    [$volunteers] = addPublishedForm($organization, $event, 'Bénévoles');
    [$again] = addPublishedForm($organization, $event, 'Bénévoles');

    app(CurrentOrganization::class)->set($organization);
    $first = Form::query()->where('event_id', $event->id)->orderBy('id')->firstOrFail();
    app(CurrentOrganization::class)->clear();

    expect($first->is_default)->toBeTrue()
        ->and($first->slug)->toBe('inscription')
        ->and($volunteers->is_default)->toBeFalse()
        ->and($volunteers->slug)->toBe('benevoles')
        ->and($again->slug)->toBe('benevoles-2');
});

it('ouvre le formulaire de son lien, avec son propre thème', function (): void {
    Storage::fake('public');
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']]);
    [$volunteers] = addPublishedForm($organization, $event, 'Bénévoles', [['key' => 'mission', 'type' => 'short_text', 'label' => 'Mission souhaitée']]);

    app(CurrentOrganization::class)->set($organization);
    $volunteers->update(['settings' => ['theme' => ['header_image_path' => 'organization-images/benevoles.jpg']]]);
    app(CurrentOrganization::class)->clear();

    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/f/benevoles")->assertOk()->assertSee('commencer?formulaire=benevoles', false);
    $this->get("{$base}/commencer?formulaire=benevoles")->assertRedirect();

    $draft = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail();
    expect($draft->form_version_id)->toBe($volunteers->current_version_id);

    $this->get("{$base}/{$draft->resume_token}/identite")->assertOk()->assertSee(Storage::disk('public')->url('organization-images/benevoles.jpg'), false);
    $this->get("{$base}/{$draft->resume_token}/reponses")->assertOk()->assertSee('Mission souhaitée')->assertDontSee('>Nom<', false);

    // Le lien de l'événement ouvre toujours le formulaire par défaut, sans le bandeau des bénévoles.
    $this->get("{$base}/commencer");
    $default = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail();
    expect($default->form_version_id)->not->toBe($volunteers->current_version_id);
    $this->get("{$base}/{$default->resume_token}/identite")->assertOk()->assertDontSee('benevoles.jpg', false);
});

it('refuse un lien de formulaire inconnu ou pas encore publié', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => 'Exposants', 'fields' => []]);
    app(CurrentOrganization::class)->clear();

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/f/exposants")->assertNotFound();
    $this->get("{$base}/f/inconnu")->assertNotFound();
    $this->get("{$base}/commencer?formulaire=exposants")->assertNotFound();
});

it('change le formulaire qui répond au lien de l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    [$volunteers, $admin] = addPublishedForm($organization, $event, 'Bénévoles');

    $this->actingAs($admin)->post("/forms/{$volunteers->id}/default")->assertRedirect();
    auth()->logout();

    app(CurrentOrganization::class)->set($organization);
    expect(Form::query()->where('event_id', $event->id)->where('is_default', true)->pluck('id')->all())->toBe([$volunteers->id]);
    app(CurrentOrganization::class)->clear();

    $base = "/r/{$organization->slug}/{$event->slug}";
    $this->get("{$base}/commencer");
    expect(RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->form_version_id)
        ->toBe($volunteers->current_version_id);
});

it('ne supprime ni le formulaire par défaut ni un formulaire qui a reçu des réponses', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    [$volunteers, $admin] = addPublishedForm($organization, $event, 'Bénévoles');
    [$spare] = addPublishedForm($organization, $event, 'Exposants');
    $base = "/r/{$organization->slug}/{$event->slug}";

    app(CurrentOrganization::class)->set($organization);
    $default = Form::query()->where('event_id', $event->id)->where('is_default', true)->firstOrFail();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->delete("/forms/{$default->id}")->assertSessionHasErrors('form');

    // Les bénévoles ont répondu : leur formulaire reste, pour lire leurs réponses.
    registerGuestWithAnswersOn($this, $event, $base, 'benevoles');
    $this->actingAs($admin)->delete("/forms/{$volunteers->id}")->assertSessionHasErrors('form');

    $this->actingAs($admin)->delete("/forms/{$spare->id}")->assertSessionHasNoErrors();
    expect(Form::withoutGlobalScopes()->withTrashed()->find($spare->id)->deleted_at)->not->toBeNull();
    auth()->logout();
    $this->get("{$base}/f/exposants")->assertNotFound();
});

it('liste les formulaires de l\'événement, celui par défaut en tête', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    [$volunteers, $admin] = addPublishedForm($organization, $event, 'Bénévoles');

    $this->actingAs($admin)->get("/events/{$event->id}/forms")->assertInertia(fn ($page) => $page
        ->component('Forms/Index')
        ->has('forms', 2)
        ->where('forms.0.isDefault', true)
        ->where('forms.0.url', route('guest.registration.start', [$organization->slug, $event->slug]))
        ->where('forms.1.id', $volunteers->id)
        ->where('forms.1.url', route('guest.registration.form', [$organization->slug, $event->slug, 'benevoles']))
        ->where('forms.1.isLive', true)
        ->where('forms.1.canDelete', true)
        ->where('canCreate', true));

    // Le partage du constructeur donne le lien propre du formulaire.
    $this->actingAs($admin)->get("/forms/{$volunteers->id}/edit")->assertInertia(fn ($page) => $page
        ->where('sharing.url', route('guest.registration.form', [$organization->slug, $event->slug, 'benevoles'])));

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    $this->actingAs($viewer)->get("/events/{$event->id}/forms")->assertForbidden();
});

it('réunit les questions de tous les formulaires dans les réponses de l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']]);
    addPublishedForm($organization, $event, 'Bénévoles', [
        ['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom complet'],
        ['key' => 'mission', 'type' => 'short_text', 'label' => 'Mission souhaitée'],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $questions = app(PresentEventAnswers::class)->handle($event)['questions'];
    app(CurrentOrganization::class)->clear();

    // Une clé commune n'apparaît qu'une fois, sous le libellé du formulaire par défaut.
    expect(array_column($questions, 'label', 'key'))->toBe(['nom' => 'Nom', 'mission' => 'Mission souhaitée']);
});

/**
 * Même parcours que registerGuestWithAnswers, par le lien d'un formulaire.
 */
function registerGuestWithAnswersOn(TestCase $test, Event $event, string $base, string $slug): void
{
    auth()->logout();
    $test->get("{$base}/commencer?formulaire={$slug}");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $test->post("{$base}/{$token}/identite", ['email' => 'benevole@example.com', 'first_name' => 'Awa', 'last_name' => 'Diallo']);
    $test->post("{$base}/{$token}/reponses", [])->assertSessionHasNoErrors();
    $test->post("{$base}/{$token}/recap");
}
