<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\EventPoll;
use App\Domain\Form\Models\EventPollStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\LivePolls\ManageEventPolls;

it('crée un sondage avec ses réponses', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_live_polls' => true]);

    $this->actingAs($admin)->post("/events/{$event->id}/polls", [
        'question' => 'Quel est votre plat préféré ?',
        'options' => ['Poisson', 'Viande', 'Végétarien'],
    ])->assertSessionHas('status', 'poll-created');

    $poll = EventPoll::query()->sole();
    expect($poll->question)->toBe('Quel est votre plat préféré ?')
        ->and($poll->status)->toBe(EventPollStatus::Draft)
        ->and($poll->options()->pluck('label')->all())->toBe(['Poisson', 'Viande', 'Végétarien']);
});

it('refuse un sondage avec moins de deux réponses', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_live_polls' => true]);

    $this->actingAs($admin)->post("/events/{$event->id}/polls", [
        'question' => 'Une question ?',
        'options' => ['Une seule réponse'],
    ])->assertSessionHasErrors('options');

    expect(EventPoll::query()->count())->toBe(0);
});

it('ouvre puis ferme un sondage', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_live_polls' => true]);
    $poll = app(ManageEventPolls::class)->create($event, 'Une question ?', ['Oui', 'Non']);

    $this->actingAs($admin)->post("/events/{$event->id}/polls/{$poll->id}/open")
        ->assertSessionHas('status', 'poll-opened');
    expect($poll->fresh()->status)->toBe(EventPollStatus::Open)
        ->and($poll->fresh()->opened_at)->not->toBeNull();

    $this->actingAs($admin)->post("/events/{$event->id}/polls/{$poll->id}/close")
        ->assertSessionHas('status', 'poll-closed');
    expect($poll->fresh()->status)->toBe(EventPollStatus::Closed)
        ->and($poll->fresh()->closed_at)->not->toBeNull();
});

it('liste les sondages avec leurs résultats', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_live_polls' => true]);
    $poll = app(ManageEventPolls::class)->create($event, 'Une question ?', ['Oui', 'Non']);
    $option = $poll->options()->where('label', 'Oui')->sole();
    $option->update(['vote_count' => 3]);

    $response = $this->actingAs($admin)->get("/events/{$event->id}/polls")->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('polls.0.question', 'Une question ?')
        ->where('polls.0.totalVotes', 3)
        ->where('polls.0.options.0.percent', 100));
});

it('refuse à qui n\'a pas le droit de modifier l\'événement', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_live_polls' => true]);
    $poll = app(ManageEventPolls::class)->create($event, 'Une question ?', ['Oui', 'Non']);

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->get("/events/{$event->id}/polls")->assertForbidden();
    $this->actingAs($viewer)->post("/events/{$event->id}/polls/{$poll->id}/open")->assertForbidden();
});

it('ferme la gestion tant que l\'organisateur n\'a pas activé la fonctionnalité', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_live_polls' => false]);

    $this->actingAs($admin)->get("/events/{$event->id}/polls")->assertNotFound();
});
