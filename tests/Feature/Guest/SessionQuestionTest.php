<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Models\SessionQuestion;
use App\Domain\Form\Models\SessionQuestionStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;

/**
 * Questions au conférencier (D9), côté invité.
 *
 * @return array{organization: Organization, event: object, registration: Registration, url: string}
 */
function sessionQuestionEvent(bool $open = true): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_participant_app' => $open,
        'has_speaker_qa' => $open,
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

    $url = URL::temporarySignedRoute('guest.registration.questions', now()->addDay(), [
        $organization->slug, $event->slug, $registration->id,
    ]);

    return ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url];
}

it('pose une question et la retrouve dans la liste', function (): void {
    ['url' => $url] = sessionQuestionEvent();

    $this->post($url, ['body' => 'Quel est le secret du succès ?'])->assertSessionHas('status', 'question-asked');

    $this->get($url)->assertOk()->assertSee('Quel est le secret du succès ?');
});

it('refuse une question vide ou trop longue', function (): void {
    ['url' => $url] = sessionQuestionEvent();

    $this->post($url, ['body' => ''])->assertSessionHasErrors('body');
    $this->post($url, ['body' => str_repeat('a', 501)])->assertSessionHasErrors('body');

    expect(SessionQuestion::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('vote pour une question puis retire son vote', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url] = sessionQuestionEvent();

    app(CurrentOrganization::class)->set($organization);
    $question = SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => 'Une question déjà posée ?',
    ]);
    app(CurrentOrganization::class)->clear();

    $voteUrl = URL::temporarySignedRoute('guest.registration.questions.votes', now()->addDay(), [
        $organization->slug, $event->slug, $registration->id, $question->id,
    ]);

    $this->post($voteUrl);
    expect($question->fresh()->upvote_count)->toBe(1);

    $this->post($voteUrl);
    expect($question->fresh()->upvote_count)->toBe(0);
});

it('masque les questions masquées par l\'organisateur', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url] = sessionQuestionEvent();

    app(CurrentOrganization::class)->set($organization);
    SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => 'Question masquée par modération',
        'status' => SessionQuestionStatus::Hidden,
    ]);
    app(CurrentOrganization::class)->clear();

    $this->get($url)->assertOk()->assertDontSee('Question masquée par modération');
});

it('affiche la réponse écrite par l\'organisateur', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url] = sessionQuestionEvent();

    app(CurrentOrganization::class)->set($organization);
    SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => 'Une question répondue',
        'status' => SessionQuestionStatus::Answered,
        'answer' => 'Voici la réponse complète.',
        'answered_at' => now(),
    ]);
    app(CurrentOrganization::class)->clear();

    $this->get($url)->assertOk()->assertSee('Voici la réponse complète.');
});

it('ferme les questions tant que l\'organisateur n\'a pas ouvert la fonctionnalité', function (): void {
    ['url' => $url] = sessionQuestionEvent(open: false);

    $this->get($url)->assertNotFound();
    $this->post($url, ['body' => 'Une question'])->assertNotFound();
});

it('refuse les questions sans lien signé', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = sessionQuestionEvent();

    $this->get("/r/{$organization->slug}/{$event->slug}/inscriptions/{$registration->id}/questions")
        ->assertForbidden();
});
