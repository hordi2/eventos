<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\RegistrationNotificationPreference;

it('affiche les événements de l\'organisation avec les préférences par défaut', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($user)->get('/settings/notifications');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('registrationNotificationsEnabled', true)
        ->has('events', 1)
        ->where('events.0.notify_created', true));
});

it('désactive le réglage général des notifications', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($user)->patch('/settings/notifications', ['registration_notifications_enabled' => false])->assertRedirect();

    expect($user->fresh()->registration_notifications_enabled)->toBeFalse();
});

it('enregistre une préférence par événement', function (): void {
    ['event' => $event, 'doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($user)->patch("/settings/notifications/events/{$event->id}", [
        'notify_created' => false,
        'notify_updated' => true,
        'notify_cancelled' => true,
    ])->assertRedirect();

    $preference = RegistrationNotificationPreference::query()->where('user_id', $user->id)->where('event_id', $event->id)->firstOrFail();
    expect($preference->notify_created)->toBeFalse();
});
