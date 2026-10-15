<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\SessionQuestion;
use App\Domain\Form\Models\SessionQuestionStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

/**
 * Une inscription minimale, explicitement rattachée à $organization et
 * $event à chaque étage — même précaution que pushSubscriptionRegistration
 * (tests/Feature/Organizer/PushNotificationTest.php) : les valeurs par
 * défaut imbriquées de RegistrationFactory créeraient sinon leur propre
 * organisation, rejetée par la RLS.
 */
function sessionQuestionRegistration(Organization $organization, Event $event): Registration
{
    $form = Form::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);

    return Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
    ]);
}

it('liste les questions, masquées comprises, les plus soutenues en premier', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_speaker_qa' => true]);
    $registration = sessionQuestionRegistration($organization, $event);

    $make = fn (string $body, int $upvotes, SessionQuestionStatus $status = SessionQuestionStatus::Pending) => SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => $body,
        'upvote_count' => $upvotes,
        'status' => $status,
    ]);

    $make('Question peu soutenue', 0);
    $make('Question très soutenue', 5);
    $make('Question masquée', 1, SessionQuestionStatus::Hidden);

    $response = $this->actingAs($admin)->get("/events/{$event->id}/questions")->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('questions.0.body', 'Question très soutenue')
        ->where('questions.1.body', 'Question masquée')
        ->where('questions.2.body', 'Question peu soutenue'));
});

it('répond à une question', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_speaker_qa' => true]);
    $registration = sessionQuestionRegistration($organization, $event);
    $question = SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => 'Une question',
    ]);

    $this->actingAs($admin)->post("/events/{$event->id}/questions/{$question->id}/answer", ['answer' => 'Voici la réponse.'])
        ->assertSessionHas('status', 'question-answered');

    $fresh = $question->fresh();
    expect($fresh->status)->toBe(SessionQuestionStatus::Answered)
        ->and($fresh->answer)->toBe('Voici la réponse.')
        ->and($fresh->answered_at)->not->toBeNull();
});

it('masque puis réaffiche une question', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_speaker_qa' => true]);
    $registration = sessionQuestionRegistration($organization, $event);
    $question = SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => 'Une question',
    ]);

    $this->actingAs($admin)->post("/events/{$event->id}/questions/{$question->id}/hide")
        ->assertSessionHas('status', 'question-hidden');
    expect($question->fresh()->status)->toBe(SessionQuestionStatus::Hidden);

    $this->actingAs($admin)->post("/events/{$event->id}/questions/{$question->id}/restore")
        ->assertSessionHas('status', 'question-restored');
    expect($question->fresh()->status)->toBe(SessionQuestionStatus::Pending);
});

it('refuse à qui n\'a pas le droit de modifier l\'événement', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_speaker_qa' => true]);
    $registration = sessionQuestionRegistration($organization, $event);
    $question = SessionQuestion::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'body' => 'Une question',
    ]);

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->get("/events/{$event->id}/questions")->assertForbidden();
    $this->actingAs($viewer)->post("/events/{$event->id}/questions/{$question->id}/hide")->assertForbidden();
});

it('ferme la modération tant que l\'organisateur n\'a pas ouvert la fonctionnalité', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_speaker_qa' => false]);

    $this->actingAs($admin)->get("/events/{$event->id}/questions")->assertNotFound();
});
