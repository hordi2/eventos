<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use App\Support\Images\ResizeUploadedImage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function fakePexelsPhoto(int $id, string $source): array
{
    return [
        'id' => $id,
        'width' => 5000,
        'height' => 3333,
        'url' => "https://www.pexels.com/photo/{$id}/",
        'photographer' => 'Awa Diallo',
        'photographer_url' => 'https://www.pexels.com/@awa',
        'alt' => 'Salle de réception décorée',
        'src' => ['medium' => "https://images.pexels.com/photos/{$id}/medium.jpeg", 'large2x' => $source],
    ];
}

beforeEach(function (): void {
    config(['services.pexels.key' => 'cle-de-test']);
});

it('cherche des photos libres en français et crédite chaque photographe', function (): void {
    [, $admin] = formWithBuilderSettings();

    Http::fake([
        'api.pexels.com/v1/search*' => Http::response([
            'photos' => [fakePexelsPhoto(101, 'https://images.pexels.com/photos/101/large2x.jpeg')],
            'next_page' => 'https://api.pexels.com/v1/search?page=2',
        ]),
    ]);

    $reponse = $this->actingAs($admin)->getJson('/media-library/stock?query=mariage')->assertOk();

    expect($reponse->json('photos.0'))->toMatchArray(['id' => 101, 'photographer' => 'Awa Diallo', 'alt' => 'Salle de réception décorée']);
    expect($reponse->json('has_more'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'cle-de-test')
        && $request['query'] === 'mariage'
        && $request['locale'] === 'fr-FR');
});

it('copie la photo choisie dans « Mes images », redimensionnée et créditée', function (): void {
    Storage::fake('public');
    [$organization, $admin] = formWithBuilderSettings();
    // Garder l'objet : son fichier temporaire disparaît avec lui.
    $source = UploadedFile::fake()->image('source.jpg', 1880, 1250);
    $jpeg = (string) file_get_contents($source->getRealPath());

    Http::fake([
        'api.pexels.com/v1/photos/202' => Http::response(fakePexelsPhoto(202, 'https://images.pexels.com/photos/202/large2x.jpeg')),
        'images.pexels.com/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $reponse = $this->actingAs($admin)->postJson('/media-library/stock/202')->assertCreated();

    expect($reponse->json('width'))->toBe(ResizeUploadedImage::MAX_WIDTH);
    expect($reponse->json('name'))->toBe('Photo de Awa Diallo (Pexels).jpg');
    expect($reponse->json('path'))->toStartWith(OrganizationImage::DIRECTORY."/{$organization->id}/");
    Storage::disk('public')->assertExists((string) $reponse->json('path'));
    expect(OrganizationImage::query()->count())->toBe(1);
});

it('refuse de télécharger une photo servie ailleurs que chez Pexels', function (): void {
    Storage::fake('public');
    [, $admin] = formWithBuilderSettings();

    Http::fake([
        'api.pexels.com/v1/photos/303' => Http::response(fakePexelsPhoto(303, 'http://169.254.169.254/latest/meta-data')),
    ]);

    $this->actingAs($admin)->postJson('/media-library/stock/303')->assertStatus(503)->assertJsonPath('message', "Cette photo n'est plus disponible dans la bibliothèque.");

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '169.254.169.254'));
    expect(OrganizationImage::query()->count())->toBe(0);
});

it('explique qu\'il manque la clé, sans appeler Pexels', function (): void {
    config(['services.pexels.key' => null]);
    [, $admin] = formWithBuilderSettings();
    Http::fake();

    $this->actingAs($admin)->getJson('/media-library/stock')
        ->assertStatus(503)
        ->assertJsonPath('message', "La bibliothèque de photos n'est pas encore configurée sur cette installation (clé Pexels manquante).");

    Http::assertNothingSent();
});

it('traduit une panne de Pexels en message clair, et réserve la bibliothèque aux éditeurs', function (): void {
    [$organization, $admin] = formWithBuilderSettings();
    Http::fake(['api.pexels.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429)]);

    $this->actingAs($admin)->getJson('/media-library/stock?query=gala')
        ->assertStatus(503)
        ->assertJsonPath('message', 'La bibliothèque de photos ne répond pas pour le moment. Réessayez dans quelques minutes.');

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->getJson('/media-library/stock')->assertForbidden();
    $this->actingAs($viewer)->postJson('/media-library/stock/1')->assertForbidden();
});
