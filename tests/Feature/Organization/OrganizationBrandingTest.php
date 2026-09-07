<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\ThemeMode;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

it('affiche la page de charte graphique avec des valeurs par défaut', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get('/organization/branding');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Organization/Branding')
        ->where('branding.logo_url', null)
        ->where('branding.primary_color', null)
        ->where('branding.theme_mode', 'light'));
});

it('enregistre la couleur principale et le mode d\'affichage de l\'organisation', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->patchJson('/organization/branding', [
        'primary_color' => '#3366ff',
        'theme_mode' => 'dark',
    ]);

    $response->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $fresh = Organization::query()->findOrFail($organization->id);
    expect($fresh->primary_color)->toBe('#3366ff');
    expect($fresh->theme_mode)->toBe(ThemeMode::Dark);
    app(CurrentOrganization::class)->clear();
});

it('refuse un mode d\'affichage invalide', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->patchJson('/organization/branding', [
        'primary_color' => '#3366ff',
        'theme_mode' => 'rainbow',
    ]);

    $response->assertUnprocessable();
});

it('refuse une couleur qui n\'est pas un code hexadécimal valide', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->patchJson('/organization/branding', ['primary_color' => 'bleu']);

    $response->assertUnprocessable();
});

it('téléverse un logo d\'organisation et l\'expose via une URL publique', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/organization/branding/logo', [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $path = Organization::query()->findOrFail($organization->id)->logo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
    app(CurrentOrganization::class)->clear();
});

it('refuse la charte graphique à un rôle sans la capacité manageBranding', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/organization/branding');

    $response->assertForbidden();
});
