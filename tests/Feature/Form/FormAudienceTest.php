<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Registration\BuildGuestVisibilityContext;

it('pose une question selon que l\'invité vient ou décline', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $version = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'menu', 'type' => 'short_text', 'label' => 'Choix du menu'],
            ['key' => 'mot', 'type' => 'short_text', 'label' => 'Un mot pour les mariés', 'config' => ['show_if' => 'always']],
            ['key' => 'raison', 'type' => 'short_text', 'label' => 'Voulez-vous nous dire pourquoi ?', 'config' => ['show_if' => 'not_attending']],
        ],
    ])->latestVersion();
    $evaluate = app(EvaluateFormVisibility::class);

    $attending = $evaluate->handle($version, [], new FormVisibilityContext(attending: true));
    $declining = $evaluate->handle($version, [], new FormVisibilityContext(attending: false));

    expect([$attending['menu']['visible'], $attending['mot']['visible'], $attending['raison']['visible']])->toBe([true, true, false]);
    expect([$declining['menu']['visible'], $declining['mot']['visible'], $declining['raison']['visible']])->toBe([false, true, true]);
});

it('n\'applique aucun public sans contexte d\'invité, comme avant les réglages de bloc', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $version = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [['key' => 'raison', 'type' => 'short_text', 'label' => 'Pourquoi ?', 'config' => ['show_if' => 'not_attending']]],
    ])->latestVersion();

    expect(app(EvaluateFormVisibility::class)->handle($version, [])['raison']['visible'])->toBeTrue();
});

it('réserve une question aux invités dont le contact porte un des tags choisis', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $vip = Tag::factory()->create(['organization_id' => $organization->id, 'name' => 'VIP']);
    $contact = Contact::factory()->create(['organization_id' => $organization->id, 'email' => 'vip@example.com']);
    $contact->tags()->attach($vip->id, ['organization_id' => $organization->id, 'created_at' => now()]);
    $version = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [['key' => 'loge', 'type' => 'short_text', 'label' => 'Accès à la loge', 'config' => ['tag_ids' => [$vip->id]]]],
    ])->latestVersion();
    $evaluate = app(EvaluateFormVisibility::class);
    $contextFor = fn (string $email) => app(BuildGuestVisibilityContext::class)->handle($organization->id, $email, true);

    expect($evaluate->handle($version, [], $contextFor(' VIP@example.com '))['loge']['visible'])->toBeTrue();
    expect($evaluate->handle($version, [], $contextFor('inconnu@example.com'))['loge']['visible'])->toBeFalse();
});
