<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Page\Models\Page;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

it('affiche la page d\'édition avec des valeurs par défaut quand rien n\'a encore été personnalisé', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get("/events/{$event->id}/page");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Pages/Edit')
        ->where('page.banner_url', null)
        ->where('page.program_items', [])
        ->where('page.faq_items', []));
});

it('enregistre le programme et la FAQ de la page événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->patchJson("/events/{$event->id}/page", [
        'meta_description' => 'Une belle soirée à ne pas manquer.',
        'program_items' => [
            ['time' => '18h00', 'title' => 'Accueil', 'description' => null],
        ],
        'faq_items' => [
            ['question' => 'Faut-il un billet ?', 'answer' => 'Non, l\'entrée est libre.'],
        ],
    ]);

    $response->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $page = Page::query()->where('event_id', $event->id)->firstOrFail();
    expect($page->meta_description)->toBe('Une belle soirée à ne pas manquer.');
    expect($page->program_items)->toHaveCount(1);
    expect($page->faq_items)->toHaveCount(1);
    app(CurrentOrganization::class)->clear();
});

it('téléverse une bannière et l\'expose via une URL publique', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post("/events/{$event->id}/page/banner", [
        'banner' => UploadedFile::fake()->image('banniere.jpg'),
    ]);

    $response->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $page = Page::query()->where('event_id', $event->id)->firstOrFail();
    expect($page->banner_path)->not->toBeNull();
    Storage::disk('public')->assertExists($page->banner_path);
    app(CurrentOrganization::class)->clear();
});

it('refuse l\'édition de la page événement à un rôle sans la capacité updateEvents', function (): void {
    ['event' => $event, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $response = $this->actingAs($viewer)->get("/events/{$event->id}/page");

    $response->assertForbidden();
});
