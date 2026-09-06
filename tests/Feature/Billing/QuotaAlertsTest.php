<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Actions\CheckQuotaAlerts;
use App\Mail\QuotaAlertMail;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('envoie une alerte au propriétaire quand le quota atteint 100 %, une seule fois par mois', function (): void {
    Mail::fake();
    // active_events desserré à 10 : sinon le seul événement publié par
    // makeGuestReadyEvent() (1/1) franchirait lui aussi 100 %, brouillant ce
    // test qui ne porte que sur le quota d'inscriptions.
    config(['plans.free.registrations_per_month' => 1, 'plans.free.active_events' => 10]);
    // Type fixé à "corporate" : sans téléphone requis à l'identité, voir le
    // docblock de QuotaEnforcementTest::beginAndSubmitRegistration().
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    $this->get("/r/{$organization->slug}/{$event->slug}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => 'invite@example.com']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/reponses", []);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$token}/recap");

    app(CheckQuotaAlerts::class)->handle();
    app(CheckQuotaAlerts::class)->handle();

    Mail::assertSent(QuotaAlertMail::class, 1);

    app(CurrentOrganization::class)->set($organization);
    expect(DB::table('quota_alerts')->where('organization_id', $organization->id)->count())->toBe(1);
    app(CurrentOrganization::class)->clear();
});

it('n\'envoie aucune alerte tant que le quota n\'est pas atteint à 80 %', function (): void {
    Mail::fake();
    config(['plans.free.registrations_per_month' => 100, 'plans.free.active_events' => 10]);
    makeGuestReadyEvent();

    app(CheckQuotaAlerts::class)->handle();

    Mail::assertNothingSent();
});
