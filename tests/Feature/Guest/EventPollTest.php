<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\EventPollVote;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\LivePolls\ManageEventPolls;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;

/**
 * Sondages en direct (D9), côté invité.
 *
 * @return array{organization: Organization, event: object, registration: Registration, url: string}
 */
function eventPollEvent(bool $open = true): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_participant_app' => $open,
        'has_live_polls' => $open,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
    ]);
    app(CurrentOrganization::class)->clear();

    $url = URL::temporarySignedRoute('guest.registration.polls', now()->addDay(), [
        $organization->slug, $event->slug, $registration->id,
    ]);

    return ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url];
}

it('affiche un sondage ouvert et permet de voter', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url] = eventPollEvent();

    app(CurrentOrganization::class)->set($organization);
    $poll = app(ManageEventPolls::class)->create($event, 'Quelle entrée préférez-vous ?', ['Salade', 'Soupe']);
    app(ManageEventPolls::class)->open($poll);
    $optionId = $poll->options()->where('label', 'Salade')->sole()->id;
    app(CurrentOrganization::class)->clear();

    $html = $this->get($url)->assertOk()->assertSee('Quelle entrée préférez-vous ?')->getContent();

    preg_match('/action="([^"]+)"/', $html, $matches);
    $voteUrl = html_entity_decode($matches[1]);

    $this->post($voteUrl)->assertSessionHas('status', 'poll-voted');

    expect(EventPollVote::query()->withoutGlobalScopes()->where('registration_id', $registration->id)->sole()->event_poll_option_id)
        ->toBe($optionId);

    $this->get($url)->assertOk()->assertSee('100%');
});

it('change de vote tant que le sondage reste ouvert', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = eventPollEvent();

    app(CurrentOrganization::class)->set($organization);
    $poll = app(ManageEventPolls::class)->create($event, 'Une question ?', ['Option A', 'Option B']);
    app(ManageEventPolls::class)->open($poll);
    $optionA = $poll->options()->where('label', 'Option A')->sole();
    $optionB = $poll->options()->where('label', 'Option B')->sole();
    app(CurrentOrganization::class)->clear();

    $voteUrl = fn ($option) => URL::temporarySignedRoute('guest.registration.polls.votes', now()->addDay(), [
        $organization->slug, $event->slug, $registration->id, $poll->id, $option->id,
    ]);

    $this->post($voteUrl($optionA));
    $this->post($voteUrl($optionB));

    expect($optionA->fresh()->vote_count)->toBe(0)
        ->and($optionB->fresh()->vote_count)->toBe(1)
        ->and(EventPollVote::query()->withoutGlobalScopes()->where('event_poll_id', $poll->id)->count())->toBe(1);
});

it('ne compte pas un vote sur un sondage fermé', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = eventPollEvent();

    app(CurrentOrganization::class)->set($organization);
    $poll = app(ManageEventPolls::class)->create($event, 'Une question ?', ['Oui', 'Non']);
    app(ManageEventPolls::class)->open($poll);
    app(ManageEventPolls::class)->close($poll);
    $option = $poll->options()->where('label', 'Oui')->sole();
    app(CurrentOrganization::class)->clear();

    $voteUrl = URL::temporarySignedRoute('guest.registration.polls.votes', now()->addDay(), [
        $organization->slug, $event->slug, $registration->id, $poll->id, $option->id,
    ]);

    $this->post($voteUrl);

    expect($option->fresh()->vote_count)->toBe(0);
});

it('cache les sondages en brouillon', function (): void {
    ['organization' => $organization, 'event' => $event, 'url' => $url] = eventPollEvent();

    app(CurrentOrganization::class)->set($organization);
    app(ManageEventPolls::class)->create($event, 'Sondage pas encore ouvert', ['A', 'B']);
    app(CurrentOrganization::class)->clear();

    $this->get($url)->assertOk()->assertDontSee('Sondage pas encore ouvert');
});

it('ferme les sondages tant que l\'organisateur n\'a pas ouvert la fonctionnalité', function (): void {
    ['url' => $url] = eventPollEvent(open: false);

    $this->get($url)->assertNotFound();
});

it('refuse les sondages sans lien signé', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = eventPollEvent();

    $this->get("/r/{$organization->slug}/{$event->slug}/inscriptions/{$registration->id}/sondages")
        ->assertForbidden();
});
