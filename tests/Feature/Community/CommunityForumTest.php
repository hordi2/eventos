<?php

declare(strict_types=1);

use App\Domain\Community\Models\CommunityCategory;
use App\Domain\Community\Models\CommunityTopic;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;

it('refuse l\'accès au forum sans authentification', function (): void {
    $this->get('/community')->assertRedirect('/login');
});

it('liste les sujets, toutes organisations confondues', function (): void {
    ['doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);
    $author = User::factory()->create();
    CommunityTopic::factory()->for($author)->create(['title' => 'Comment gérer un gros événement ?']);

    $this->actingAs($viewer)->get('/community')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('topics.data', 1));
});

it('crée un sujet et le publie sur le forum partagé', function (): void {
    ['doorStaff' => $author] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($author)->post('/community', [
        'category' => CommunityCategory::Troubleshooting->value,
        'title' => "Problème avec l'export CSV",
        'body' => "L'export CSV ne contient pas les colonnes personnalisées.",
    ]);

    $topic = CommunityTopic::query()->where('title', "Problème avec l'export CSV")->firstOrFail();
    $response->assertRedirect("/community/{$topic->id}");
    expect($topic->user_id)->toBe($author->id);
});

it('affiche un sujet avec ses réponses', function (): void {
    ['doorStaff' => $reader] = makeCheckInEvent(MembershipRole::Viewer);
    $author = User::factory()->create();
    $topic = CommunityTopic::factory()->for($author)->create(['title' => 'Astuce plan de table']);

    $this->actingAs($reader)->get("/community/{$topic->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('topic.title', 'Astuce plan de table'));
});

it('répond à un sujet créé par un membre d\'une autre organisation', function (): void {
    ['doorStaff' => $replier] = makeCheckInEvent(MembershipRole::Admin);
    $author = User::factory()->create();
    $topic = CommunityTopic::factory()->for($author)->create();

    $response = $this->actingAs($replier)->post("/community/{$topic->id}/reponses", [
        'body' => 'Merci pour la question, voici ce qui a marché pour nous.',
    ]);

    $response->assertRedirect("/community/{$topic->id}");
    expect($topic->posts()->count())->toBe(1);
    expect($topic->posts()->first()->user_id)->toBe($replier->id);
});

it('filtre les sujets par catégorie', function (): void {
    ['doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);
    $author = User::factory()->create();
    CommunityTopic::factory()->for($author)->create(['category' => CommunityCategory::Suggestions]);
    CommunityTopic::factory()->for($author)->create(['category' => CommunityCategory::Troubleshooting]);

    $this->actingAs($viewer)->get('/community?category=suggestions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('topics.data', 1)
            ->where('topics.data.0.category', 'suggestions'));
});
