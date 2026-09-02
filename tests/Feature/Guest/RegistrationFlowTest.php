<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Hash;

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{organization: Organization, event: Event}
 */
function makeGuestReadyEvent(array $fields = [], array $eventOverrides = []): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $event = Event::factory()->for($organization)->published()->create($eventOverrides);

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => 'Inscription', 'fields' => $fields]);
    app(PublishFormVersion::class)->handle($form, $admin);

    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event];
}

it('parcourt les trois étapes et confirme une inscription', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([
        ['key' => 'allergies', 'type' => 'short_text', 'label' => 'Allergies'],
    ]);

    $start = $this->get("/r/{$organization->slug}/{$event->slug}");
    $start->assertRedirect();
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->firstOrFail()->resume_token;

    $identity = $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", [
        'email' => 'invite@example.com',
        'first_name' => 'Jean',
        'last_name' => 'Kalala',
    ]);
    $identity->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$token}/reponses");

    $answers = $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/reponses", [
        'allergies' => 'Arachides',
    ]);
    $answers->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$token}/recap");

    $confirm = $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/recap");
    $confirm->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$token}/confirmation");

    $registration = Registration::withoutGlobalScopes()->where('email', 'invite@example.com')->first();
    expect($registration)->not->toBeNull();
    expect($registration->status->value)->toBe('confirmed');

    $confirmationPage = $this->get("/r/{$organization->slug}/{$event->slug}/{$token}/confirmation");
    $confirmationPage->assertOk();
    $confirmationPage->assertSee('Inscription confirmée');
});

it('redirige vers la page « déjà inscrit » en cas de doublon', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $firstStart = $this->get("/r/{$organization->slug}/{$event->slug}");
    $firstToken = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$firstToken}/identite", ['email' => 'double@example.com']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$firstToken}/reponses", []);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$firstToken}/recap");

    $this->get("/r/{$organization->slug}/{$event->slug}");
    $secondToken = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$secondToken}/identite", ['email' => 'Double@Example.com']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$secondToken}/reponses", []);

    $confirm = $this->post("/r/{$organization->slug}/{$event->slug}/{$secondToken}/recap");
    $confirm->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$secondToken}/deja-inscrit");

    $duplicatePage = $this->get("/r/{$organization->slug}/{$event->slug}/{$secondToken}/deja-inscrit");
    $duplicatePage->assertOk();
    $duplicatePage->assertSee('déjà inscrit');
});

it('affiche la page fermée quand l\'événement affiche complet sans liste d\'attente', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['capacity' => 1, 'allow_waitlist' => false]);

    $this->get("/r/{$organization->slug}/{$event->slug}");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => 'premier@example.com']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/reponses", []);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/recap");

    $second = $this->get("/r/{$organization->slug}/{$event->slug}");
    $second->assertOk();
    $second->assertSee('complet');
});

it('bloque l\'accès à un événement protégé par mot de passe sans le bon mot de passe', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], [
        'access_mode' => 'password',
        'password_hash' => Hash::make('secret123'),
    ]);

    $blocked = $this->get("/r/{$organization->slug}/{$event->slug}");
    $blocked->assertRedirect("/r/{$organization->slug}/{$event->slug}/mot-de-passe");

    $wrong = $this->post("/r/{$organization->slug}/{$event->slug}/mot-de-passe", ['password' => 'mauvais']);
    $wrong->assertSessionHasErrors('password');

    $right = $this->post("/r/{$organization->slug}/{$event->slug}/mot-de-passe", ['password' => 'secret123']);
    $right->assertRedirect("/r/{$organization->slug}/{$event->slug}");

    $afterUnlock = $this->get("/r/{$organization->slug}/{$event->slug}");
    $afterUnlock->assertRedirect();
});

it('retourne 404 pour un événement non publié', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    $this->get("/r/{$organization->slug}/{$event->slug}")->assertNotFound();
});
