<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

it('lance une simulation au premier écran du parcours, avec la dernière version même non publiée', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();
    $event = Event::query()->findOrFail($form->event_id);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($admin)->get("/forms/{$form->id}/preview");

    $draft = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole();
    expect($draft->is_test)->toBeTrue()
        ->and($draft->form_version_id)->toBe($form->latestVersion()->id);
    $response->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$draft->resume_token}/identite");

    // L'événement est encore « Inédit » : la simulation y entre quand même.
    $this->get("/r/{$organization->slug}/{$event->slug}/{$draft->resume_token}/identite")
        ->assertOk()
        ->assertSee("Simulation d'inscription : rien ne sera enregistré", false);
});

it('va jusqu\'au bout du parcours sans rien enregistrer', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([['key' => 'regime', 'type' => 'short_text', 'label' => 'Régime alimentaire']]);
    [$form, $admin] = formAndAdminOf($organization, $event);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->actingAs($admin)->get("/forms/{$form->id}/preview");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    $this->post("{$base}/{$token}/identite", ['email' => 'test@example.com', 'first_name' => 'Awa', 'last_name' => 'Diallo'])
        ->assertRedirect("{$base}/{$token}/reponses");
    $this->get("{$base}/{$token}/reponses")->assertOk()->assertSee('Régime alimentaire');
    $this->post("{$base}/{$token}/reponses", ['regime' => 'Végétarien'])->assertRedirect("{$base}/{$token}/recap");
    $this->get("{$base}/{$token}/recap")->assertOk()->assertSee('Végétarien');
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/simulation-terminee");

    $this->get("{$base}/{$token}/simulation-terminee")
        ->assertOk()
        ->assertSee('Simulation terminée')
        ->assertSee(route('forms.preview', $form->id), false)
        ->assertSee(route('forms.edit', $form->id), false);

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->count())->toBe(0)
        ->and(Attendee::query()->count())->toBe(0)
        ->and(RegistrationDraft::query()->sole()->submitted_at)->not->toBeNull();
});

it('traverse un événement réservé à sa liste d\'invités sans invitation', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['access_mode' => EventAccessMode::ClosedList]);
    [$form, $admin] = formAndAdminOf($organization, $event);

    $this->actingAs($admin)->get("/forms/{$form->id}/preview");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    $this->get("/r/{$organization->slug}/{$event->slug}/{$token}/identite")->assertOk();
    // Sans simulation, la porte reste fermée.
    auth()->logout();
    $this->flushSession();
    $this->get("/r/{$organization->slug}/{$event->slug}/commencer")->assertRedirect("/r/{$organization->slug}/{$event->slug}/retrouver-mon-invitation");
});

it('ne montre la fin de simulation qu\'à une simulation', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    $this->get("{$base}/{$token}/simulation-terminee")->assertNotFound();
});

/**
 * Le formulaire d'un événement prêt pour les invités, et un administrateur
 * qui peut le modifier.
 *
 * @return array{0: Form, 1: User}
 */
function formAndAdminOf(Organization $organization, Event $event): array
{
    app(CurrentOrganization::class)->set($organization);
    $form = Form::query()->where('event_id', $event->id)->sole();
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    return [$form, $admin];
}
