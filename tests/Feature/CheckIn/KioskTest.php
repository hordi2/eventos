<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;

it('ouvre le kiosque avec un code à quatre chiffres', function (): void {
    ['event' => $event, 'doorStaff' => $staff] = makeCheckInEvent();

    // Sans code, pas de kiosque.
    $this->actingAs($staff)->get("/events/{$event->id}/kiosk")->assertRedirect(route('events.check-in.index', $event->id));

    $this->actingAs($staff)->post("/events/{$event->id}/kiosk", ['code' => '12'])->assertSessionHasErrors('code');

    $this->actingAs($staff)->post("/events/{$event->id}/kiosk", ['code' => '4071'])
        ->assertRedirect(route('events.kiosk.show', $event->id));

    $this->actingAs($staff)->get("/events/{$event->id}/kiosk")->assertInertia(fn ($page) => $page
        ->component('CheckIn/Kiosk')
        ->where('event.id', $event->id));
});

it('ne laisse quitter le kiosque qu\'avec le bon code', function (): void {
    ['event' => $event, 'doorStaff' => $staff] = makeCheckInEvent();

    $this->actingAs($staff)->post("/events/{$event->id}/kiosk", ['code' => '4071']);

    $this->actingAs($staff)->post("/events/{$event->id}/kiosk/exit", ['code' => '1111'])->assertSessionHasErrors('code');
    $this->actingAs($staff)->get("/events/{$event->id}/kiosk")->assertOk();

    $this->actingAs($staff)->post("/events/{$event->id}/kiosk/exit", ['code' => '4071'])
        ->assertRedirect(route('events.check-in.index', $event->id));

    // Le kiosque est refermé : la tablette revient à l'écran d'accueil.
    $this->actingAs($staff)->get("/events/{$event->id}/kiosk")->assertRedirect(route('events.check-in.index', $event->id));
});

it('cherche un invité par son nom, sans jamais livrer la liste entière', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $staff] = makeCheckInEvent();
    $attendee = makeCheckedInAttendee($organization, $event);

    // Un prénom accentué, posé explicitement : la factory en tire un au
    // hasard, et la recherche doit marcher pour « Désiré » comme pour
    // « Marie ».
    app(CurrentOrganization::class)->set($organization);
    $attendee->update(['first_name' => 'Désirée', 'last_name' => 'Mukendi']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($staff)->post("/events/{$event->id}/kiosk", ['code' => '4071']);

    // Moins de trois lettres : rien ne sort. Le terme passe par urlencode :
    // un accent écrit tel quel dans l'adresse n'arrive pas entier au serveur.
    $this->actingAs($staff)->getJson("/events/{$event->id}/kiosk/search?q=".urlencode('Dé'))
        ->assertOk()
        ->assertJsonPath('guests', []);

    $this->actingAs($staff)->getJson("/events/{$event->id}/kiosk/search?q=".urlencode('Désirée'))
        ->assertOk()
        ->assertJsonPath('guests.0.name', 'Désirée Mukendi');
});

it('réserve le kiosque aux membres qui peuvent faire le check-in', function (): void {
    ['event' => $event] = makeCheckInEvent();
    [, $viewer] = organizationWithContactRole(MembershipRole::Viewer);

    $this->actingAs($viewer)->post("/events/{$event->id}/kiosk", ['code' => '4071'])->assertNotFound();
});

it('refuse la recherche du kiosque hors du mode kiosque', function (): void {
    ['event' => $event, 'doorStaff' => $staff] = makeCheckInEvent();

    $this->actingAs($staff)->getJson("/events/{$event->id}/kiosk/search?q=marie")->assertForbidden();
});
