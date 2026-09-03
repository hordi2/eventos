<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;

it('exige le téléphone à l\'identité pour un événement personnel', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Wedding]);

    $this->get("/r/{$organization->slug}/{$event->slug}");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->firstOrFail()->resume_token;

    $withoutPhone = $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => 'invite@example.com']);
    $withoutPhone->assertSessionHasErrors('phone');

    $withPhone = $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", [
        'email' => 'invite@example.com',
        'phone' => '+243812345678',
    ]);
    $withPhone->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$token}/reponses");
});

it('laisse le téléphone optionnel à l\'identité pour un événement corporate', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    $this->get("/r/{$organization->slug}/{$event->slug}");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->firstOrFail()->resume_token;

    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => 'invite@example.com'])
        ->assertRedirect("/r/{$organization->slug}/{$event->slug}/{$token}/reponses");
});

it('exige aussi le téléphone lors de la modification d\'une inscription à un événement personnel', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Birthday, 'allow_guest_edit' => true]);

    $this->get("/r/{$organization->slug}/{$event->slug}");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => 'invite@example.com', 'phone' => '+243812345678']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/reponses", []);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/recap");

    $registration = Registration::withoutGlobalScopes()->where('email', 'invite@example.com')->firstOrFail();
    $editUrl = $this->app['url']->temporarySignedRoute('guest.registration.edit', now()->addHour(), [$organization->slug, $event->slug, $registration->id]);

    $response = $this->post($editUrl, [
        'email' => 'invite@example.com',
        'phone' => '',
    ]);

    $response->assertSessionHasErrors('phone');
});
