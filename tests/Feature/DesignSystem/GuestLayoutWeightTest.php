<?php

declare(strict_types=1);

it('le layout invité pèse moins de 100 Ko sans contenu (HTML + CSS)', function (): void {
    $html = view('guest.layout')->render();

    $manifestPath = public_path('build/manifest.json');
    expect($manifestPath)->toBeReadableFile();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    $cssFile = $manifest['resources/css/guest.css']['file'] ?? null;

    expect($cssFile)->not->toBeNull('resources/css/guest.css doit être un point d\'entrée Vite compilé.');

    $cssPath = public_path('build/'.$cssFile);
    expect($cssPath)->toBeReadableFile();

    $totalBytes = strlen($html) + filesize($cssPath);

    expect($totalBytes)->toBeLessThan(100 * 1024);
});

it('les pages invité ne référencent jamais le bundle React de l\'organisateur', function (): void {
    $html = view('guest.layout')->render();

    expect($html)->not->toContain('organizer/app');
});
