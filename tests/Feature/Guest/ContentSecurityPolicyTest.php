<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;

/**
 * CSP stricte sur les pages publiques (§7 du CLAUDE.md).
 *
 * public/hot est un fichier d'état partagé avec le serveur Vite de
 * rechargement à chaud (npm run dev) : qu'il traîne ou non sur la machine
 * qui exécute les tests ne doit rien dire de l'environnement réel visé par
 * la CSP. On le neutralise pour la durée de ce fichier, et on rend
 * exactement ce qu'on a trouvé en partant.
 */
beforeEach(function (): void {
    $this->hotFile = public_path('hot');
    $this->hotFileBackup = is_file($this->hotFile) ? file_get_contents($this->hotFile) : null;
    @unlink($this->hotFile);
});

afterEach(function (): void {
    if ($this->hotFileBackup !== null) {
        file_put_contents($this->hotFile, $this->hotFileBackup);
    } else {
        @unlink($this->hotFile);
    }
});

it('pose une CSP stricte sur la page RSVP publique', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $response = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk();

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->not->toBeNull()
        ->and($policy)->toContain("default-src 'self'")
        // Alpine.js évalue ses directives via new Function() : sans
        // 'unsafe-eval', toute page invité cesserait de fonctionner.
        ->and($policy)->toContain("script-src 'self' 'unsafe-eval'")
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("frame-ancestors 'self'")
        // Jamais de script ni de style injectés en ligne par une faille XSS.
        ->and($policy)->not->toContain('unsafe-inline https://www.googletagmanager.com')
        ->and($policy)->not->toMatch("/script-src[^;]*'unsafe-inline'/");
});

it('pose la même CSP sur les pages statiques publiques', function (): void {
    $this->get('/terms')->assertOk()
        ->assertHeader('Content-Security-Policy');

    $this->get('/status')->assertOk()
        ->assertHeader('Content-Security-Policy');
});

it('ne pose aucune CSP sur le back-office organisateur', function (): void {
    [$organization, $owner] = organizationWithContactRole(MembershipRole::Owner);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/dashboard')
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy');
});

it('ouvre les seuls hôtes d\'images réellement configurés', function (): void {
    config(['filesystems.disks.public.url' => 'https://assets.itaza.test/storage']);
    config(['filesystems.disks.s3.url' => 'https://cdn.itaza-r2.test']);

    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $policy = $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->headers->get('Content-Security-Policy');

    expect($policy)->toContain('img-src \'self\' data: assets.itaza.test cdn.itaza-r2.test');
});

it('n\'applique pas la CSP tant que le serveur Vite de rechargement à chaud est actif', function (): void {
    $hot = public_path('hot');
    file_put_contents($hot, 'http://localhost:5173');

    try {
        ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

        $this->get("/r/{$organization->slug}/{$event->slug}")
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    } finally {
        @unlink($hot);
    }
});
