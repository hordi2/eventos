<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Billing\GetOrganizationUsage;
use App\Support\MultiTenancy\CurrentOrganization;

it('compte les inscriptions du mois en cours et les événements actifs', function (): void {
    config(['plans.free.registrations_per_month' => 100]);
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    $this->get("/r/{$organization->slug}/{$event->slug}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => 'invite@example.com']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/reponses", []);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/recap");

    app(CurrentOrganization::class)->set($organization);
    $usage = app(GetOrganizationUsage::class)->handle($organization->fresh());
    app(CurrentOrganization::class)->clear();

    expect($usage->registrationsThisMonth)->toBe(1);
    expect($usage->registrationsQuota)->toBe(100);
    expect($usage->activeEvents)->toBe(1);
    expect($usage->percentages()['registrations'])->toBe(1);
});

it('n\'expose aucune capacité manageBilling à un rôle autre que Owner', function (): void {
    ['event' => $event, 'doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/billing');

    $response->assertForbidden();
});
