<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * event_id/form_version_id sont des clés étrangères réelles : chaque niveau
 * doit explicitement partager la même organisation, sans quoi les factories
 * imbriquées en créeraient chacune une nouvelle, rejetée par la RLS (même
 * gotcha que ConfirmPromotedRegistrationTest).
 */
function registerContactForEvent(
    Organization $organization,
    Event $event,
    Contact $contact,
    RegistrationStatus $status,
    ?CarbonImmutable $checkedInAt = null,
): Registration {
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);

    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'contact_id' => $contact->id,
        'status' => $status,
    ]);

    Attendee::factory()->create([
        'organization_id' => $organization->id,
        'registration_id' => $registration->id,
        'is_primary' => true,
        'checked_in_at' => $checkedInAt,
    ]);

    return $registration;
}

it('classe les contacts dans les 4 segments déductibles de Registration.status', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHours(3)]);

    $sansReponse = Contact::factory()->for($organization)->create(['last_name' => 'Sans réponse']);
    $confirme = Contact::factory()->for($organization)->create(['last_name' => 'Confirmé']);
    $decline = Contact::factory()->for($organization)->create(['last_name' => 'Décliné']);

    registerContactForEvent($organization, $event, $confirme, RegistrationStatus::Confirmed);
    registerContactForEvent($organization, $event, $decline, RegistrationStatus::Cancelled);

    $sansReponsePage = $this->actingAs($admin)->get(route('events.segments.show', [$event, 'sans_reponse']));
    $sansReponsePage->assertOk();
    $sansReponsePage->assertInertia(fn ($page) => $page
        ->where('contacts.data.0.id', $sansReponse->id)
        ->has('contacts.data', 1));

    $confirmesPage = $this->actingAs($admin)->get(route('events.segments.show', [$event, 'confirmes']));
    $confirmesPage->assertInertia(fn ($page) => $page
        ->where('contacts.data.0.id', $confirme->id)
        ->has('contacts.data', 1));

    $declinesPage = $this->actingAs($admin)->get(route('events.segments.show', [$event, 'declines']));
    $declinesPage->assertInertia(fn ($page) => $page
        ->where('contacts.data.0.id', $decline->id)
        ->has('contacts.data', 1));
});

it('classe présents et no-show selon le pointage, seulement après la fin de l\'événement pour no-show', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $futureEvent = Event::factory()->for($organization)->create(['start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHours(3)]);
    $present = Contact::factory()->for($organization)->create();
    $absent = Contact::factory()->for($organization)->create();

    registerContactForEvent($organization, $futureEvent, $present, RegistrationStatus::Confirmed, CarbonImmutable::now());
    registerContactForEvent($organization, $futureEvent, $absent, RegistrationStatus::Confirmed);

    $this->actingAs($admin)
        ->get(route('events.segments.show', [$futureEvent, 'presents']))
        ->assertInertia(fn ($page) => $page->where('contacts.data.0.id', $present->id)->has('contacts.data', 1));

    // L'événement n'est pas encore terminé : personne n'est encore "no-show".
    $this->actingAs($admin)
        ->get(route('events.segments.show', [$futureEvent, 'no_show']))
        ->assertInertia(fn ($page) => $page->has('contacts.data', 0));

    $pastEvent = Event::factory()->for($organization)->create(['start_at' => now()->subWeek(), 'end_at' => now()->subDays(6)]);
    $presentAtPastEvent = Contact::factory()->for($organization)->create();
    $noShow = Contact::factory()->for($organization)->create();

    registerContactForEvent($organization, $pastEvent, $presentAtPastEvent, RegistrationStatus::Confirmed, CarbonImmutable::now()->subWeek());
    registerContactForEvent($organization, $pastEvent, $noShow, RegistrationStatus::Confirmed);

    $this->actingAs($admin)
        ->get(route('events.segments.show', [$pastEvent, 'no_show']))
        ->assertInertia(fn ($page) => $page->where('contacts.data.0.id', $noShow->id)->has('contacts.data', 1));
});

it('applique un tag en masse à tous les membres actuels d\'un segment', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $tag = Tag::factory()->for($organization)->create(['name' => 'À relancer']);

    $sansReponse1 = Contact::factory()->for($organization)->create();
    $sansReponse2 = Contact::factory()->for($organization)->create();
    $confirme = Contact::factory()->for($organization)->create();
    registerContactForEvent($organization, $event, $confirme, RegistrationStatus::Confirmed);

    $this->actingAs($admin)->post(route('events.segments.apply-tag', [$event, 'sans_reponse']), ['tag_id' => $tag->id])
        ->assertRedirect(route('events.segments.show', [$event, 'sans_reponse']));

    expect($sansReponse1->fresh()->tags->pluck('id'))->toContain($tag->id);
    expect($sansReponse2->fresh()->tags->pluck('id'))->toContain($tag->id);
    expect($confirme->fresh()->tags->pluck('id'))->not->toContain($tag->id);

    // Ré-appliquer ne doit jamais échouer (contrainte unique contact_id/tag_id).
    $this->actingAs($admin)->post(route('events.segments.apply-tag', [$event, 'sans_reponse']), ['tag_id' => $tag->id])
        ->assertRedirect();
});

it('refuse l\'application d\'un tag à un rôle sans capacité updateGuests', function (): void {
    [$organization, $viewer] = organizationWithContactRole(MembershipRole::Viewer);
    $event = Event::factory()->for($organization)->create();
    $tag = Tag::factory()->for($organization)->create();

    // Viewer a viewGuests (M0.3) mais pas updateGuests : il peut consulter
    // un segment mais pas y appliquer un tag en masse.
    $this->actingAs($viewer)
        ->get(route('events.segments.show', [$event, 'sans_reponse']))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('events.segments.apply-tag', [$event, 'sans_reponse']), ['tag_id' => $tag->id])
        ->assertForbidden();
});

it('renvoie une 404 pour un segment inconnu', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->get(route('events.segments.show', [$event, 'segment-inexistant']))
        ->assertNotFound();
});
