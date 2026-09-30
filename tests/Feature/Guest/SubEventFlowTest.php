<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Form\Actions\GenerateAttendeeQrToken;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

it('propose les sessions avec leurs places et refuse deux sessions simultanées', function (): void {
    ['event' => $event, 'dinner' => $dinner, 'cocktail' => $cocktail, 'base' => $base] = guestEventWithSessions();

    $this->get("{$base}/commencer");
    $token = sessionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com']);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('Dîner de gala')
        ->assertSee('10 places restantes')
        ->assertSee('name="sessions[]"', false);

    $this->post("{$base}/{$token}/reponses", ['sessions' => [(string) $dinner->id, (string) $cocktail->id]])
        ->assertSessionHasErrors('sessions');
});

it('inscrit l\'invité à la session choisie et reconnaît son QR à l\'accueil de la session', function (): void {
    ['organization' => $organization, 'event' => $event, 'dinner' => $dinner, 'doorStaff' => $doorStaff, 'base' => $base] = guestEventWithSessions();

    $this->get("{$base}/commencer");
    $token = sessionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com', 'first_name' => 'Marie', 'last_name' => 'Lusala']);
    $this->post("{$base}/{$token}/reponses", ['sessions' => [(string) $dinner->id]])->assertRedirect("{$base}/{$token}/recap");
    $this->get("{$base}/{$token}/recap")->assertSee('Dîner de gala');
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    $registration = Registration::withoutGlobalScopes()->where('email', 'marie@example.com')->whereNull('parent_registration_id')->firstOrFail();
    $session = Registration::withoutGlobalScopes()->where('parent_registration_id', $registration->id)->firstOrFail();
    expect($session->event_id)->toBe($dinner->id);
    expect($session->status)->toBe(RegistrationStatus::Confirmed);

    app(CurrentOrganization::class)->set($organization);
    $holder = Attendee::query()->where('registration_id', $registration->id)->where('is_primary', true)->firstOrFail();
    $qrToken = app(GenerateAttendeeQrToken::class)->handle($holder, CarbonImmutable::now()->addDay());
    app(CurrentOrganization::class)->clear();

    $this->actingAs($doorStaff)->postJson("/events/{$dinner->id}/check-in/scan", ['token' => $qrToken])
        ->assertOk()
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('guest.name', 'Marie Lusala');

    $sessionAttendee = Attendee::withoutGlobalScopes()->where('registration_id', $session->id)->firstOrFail();
    expect(CheckIn::withoutGlobalScopes()->where('event_id', $dinner->id)->value('attendee_id'))->toBe($sessionAttendee->id);
});
