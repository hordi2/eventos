<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

function organizationWithFormBuilderRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('affiche le constructeur vide pour un nouvel événement', function (): void {
    [$organization, $admin] = organizationWithFormBuilderRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->get("/events/{$event->id}/form/create");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('form', null)->where('event.id', $event->id));
});

it('crée un formulaire via le constructeur et redirige vers son édition', function (): void {
    [$organization, $admin] = organizationWithFormBuilderRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->post("/events/{$event->id}/form", [
        'name' => 'Inscription générale',
        'fields' => [
            ['type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
        ],
    ]);

    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    $response->assertRedirect(route('forms.edit', $form));
    expect($form->name)->toBe('Inscription générale');
    expect($form->latestVersion()->fields)->toHaveCount(1);
});

it('refuse la création à un rôle sans createEvents', function (): void {
    [$organization, $editor] = organizationWithFormBuilderRole(MembershipRole::Editor);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($editor)->get("/events/{$event->id}/form/create");

    $response->assertForbidden();
});

it('affiche le formulaire existant avec ses champs à l\'édition', function (): void {
    [$organization, $admin] = organizationWithFormBuilderRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom complet']],
    ]);

    $response = $this->actingAs($admin)->get("/forms/{$form->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('form.name', 'Inscription générale')
        ->where('form.fields.0.key', 'nom'));
});

it('modifie en place le brouillon quand il n\'a jamais été publié', function (): void {
    [$organization, $admin] = organizationWithFormBuilderRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
    ]);
    $versionId = $form->latestVersion()->id;

    $response = $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription générale',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom et prénom']],
    ]);

    $response->assertRedirect(route('forms.edit', $form));
    $updated = $form->fresh();
    expect($updated->latestVersion()->id)->toBe($versionId);
    expect($updated->latestVersion()->fields->first()->label)->toBe('Nom et prénom');
});

it('publie le formulaire puis crée une nouvelle version à la modification suivante', function (): void {
    [$organization, $admin] = organizationWithFormBuilderRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
    ]);

    $this->actingAs($admin)->post("/forms/{$form->id}/publish")->assertRedirect(route('forms.edit', $form));
    expect($form->fresh()->latestVersion()->status)->toBe(FormVersionStatus::Published);

    $this->actingAs($admin)->patch("/forms/{$form->id}", [
        'name' => 'Inscription générale',
        'fields' => [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom complet (v2)']],
    ]);

    $updated = $form->fresh();
    expect($updated->latestVersion()->version_number)->toBe(2);
    expect($updated->latestVersion()->status)->toBe(FormVersionStatus::Draft);
    $v1 = $updated->versions()->where('version_number', 1)->first();
    expect($v1->fields->first()->label)->toBe('Nom');
});

it('bloque l\'accès à un formulaire d\'une autre organisation', function (): void {
    [, $admin] = organizationWithFormBuilderRole(MembershipRole::Admin);

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherEvent = Event::factory()->for($otherOrganization)->create();
    $otherUser = User::factory()->create();
    $otherUser->memberships()->create(['organization_id' => $otherOrganization->id, 'role' => MembershipRole::Admin]);
    $otherForm = app(CreateForm::class)->handle($otherOrganization, $otherEvent->id, $otherUser, [
        'name' => 'Autre formulaire',
        'fields' => [],
    ]);

    $response = $this->actingAs($admin)->get("/forms/{$otherForm->id}/edit");

    $response->assertNotFound();
});
