<?php

declare(strict_types=1);

use App\Domain\Event\Actions\PublishEvent;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

it('ne propose de partager le formulaire qu\'une fois publié, avec un lien de test tant que l\'événement est « Inédit »', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('sharing.available', false)
        ->where('previewUrl', route('forms.preview', $form->id)));

    $this->actingAs($admin)->post("/forms/{$form->id}/publish")->assertRedirect();

    $event = Event::query()->withoutGlobalScopes()->findOrFail($form->event_id);
    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('sharing.available', true)
        ->where('sharing.isPreview', true)
        ->where('sharing.url', fn (string $url): bool => str_contains($url, "/r/{$organization->slug}/{$event->slug}") && str_contains($url, 'signature='))
        // Pas de QR code pour un lien de test voué à expirer.
        ->where('sharing.qrCode', null)
        ->where('sharing.whatsappUrl', fn (string $url): bool => str_starts_with($url, 'https://wa.me/?text=') && str_contains($url, rawurlencode($event->title))));
});

it('donne le lien public et son QR code une fois l\'événement publié', function (): void {
    [$organization, $admin, $form] = formWithBuilderSettings();

    app(CurrentOrganization::class)->set($organization);
    app(PublishFormVersion::class)->handle($form, $admin);
    $event = Event::query()->findOrFail($form->event_id);
    app(PublishEvent::class)->handle($event, $admin);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/forms/{$form->id}/edit")->assertInertia(fn ($page) => $page
        ->where('sharing.isPreview', false)
        ->where('sharing.url', route('guest.registration.start', [$organization->slug, $event->slug]))
        ->where('sharing.qrCode', fn (string $qrCode): bool => str_starts_with($qrCode, 'data:image/png;base64,'))
        ->where('sharing.mailtoUrl', fn (string $url): bool => str_starts_with($url, 'mailto:?subject=')));
});

it('réserve la simulation aux membres qui peuvent modifier le formulaire', function (): void {
    [$organization, , $form] = formWithBuilderSettings();

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->get("/forms/{$form->id}/preview")->assertForbidden();

    // Un formulaire d'une autre organisation reste introuvable.
    [, $stranger] = organizationWithContactRole(MembershipRole::Admin);
    $this->actingAs($stranger)->get("/forms/{$form->id}/preview")->assertNotFound();
});
