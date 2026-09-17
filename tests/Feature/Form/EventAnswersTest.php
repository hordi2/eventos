<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;

it('montre la répartition par question, le total des dons et les réponses de chaque invité', function (): void {
    ['event' => $event, 'admin' => $admin, 'base' => $base] = makeAnswersReadyEvent();
    registerGuestWithAnswers($this, $event, $base);

    $this->actingAs($admin)->get("/events/{$event->id}/answers")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Answers')
            ->has('questions', 3)
            ->where('questions.0.label', 'Choix du menu')
            ->where('questions.0.answered', 2)
            // Une part de poisson tenue sur les dix : neuf restent.
            ->where('questions.0.breakdown', [
                ['label' => 'Poisson braisé', 'count' => 1, 'remaining' => 9],
                ['label' => 'Poulet mayo', 'count' => 1, 'remaining' => null],
            ])
            ->where('questions.1.totals', ['1 donateur', Money::fromMinorUnits(5000, 'XAF')->format()])
            ->where('questions.2.samples', ["Merci pour l'invitation"])
            ->has('guests', 1)
            ->where('guests.0.name', 'Marie Lusala')
            ->where('guests.0.statusLabel', 'Confirmé')
            ->where('guests.0.answers.menu', "Poisson braisé\nPaul : Poulet mayo")
            ->where('guests.0.answers.mot', "Merci pour l'invitation")
            ->where('totalGuests', 1)
            ->where('eventNav.links.answers', route('events.answers.index', $event->id)));
});

it('reste visible en lecture seule et refusée à une autre organisation', function (): void {
    ['organization' => $organization, 'event' => $event] = makeAnswersReadyEvent();

    $viewer = User::factory()->create();
    Membership::factory()->for($organization)->for($viewer)->create(['role' => MembershipRole::Viewer]);
    $this->actingAs($viewer)->get("/events/{$event->id}/answers")->assertOk();

    [, $outsider] = organizationWithContactRole(MembershipRole::Admin);
    $this->actingAs($outsider)->get("/events/{$event->id}/answers")->assertNotFound();
});

it('affiche un écran vide quand le formulaire ne pose aucune question', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    Membership::factory()->for($organization)->for($admin)->create(['role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/answers")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('questions', 0)->where('totalGuests', 0));
});
