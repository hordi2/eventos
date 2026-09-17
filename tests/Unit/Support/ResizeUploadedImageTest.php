<?php

declare(strict_types=1);

use App\Support\Images\ResizeUploadedImage;
use Illuminate\Http\UploadedFile;

it('ramène une grande image à la largeur maximale et la réencode en JPEG', function (): void {
    $resized = app(ResizeUploadedImage::class)->handle(UploadedFile::fake()->image('affiche.jpg', 4000, 3000));

    expect($resized->width)->toBe(ResizeUploadedImage::MAX_WIDTH);
    expect($resized->height)->toBe(1200);
    expect($resized->extension)->toBe('jpg');
    // Une image de 4000 × 3000 ne doit plus peser comme telle sur une page invité.
    expect(strlen($resized->content))->toBeLessThan(500 * 1024);
    expect(getimagesizefromstring($resized->content)[2])->toBe(IMAGETYPE_JPEG);
});

it('garde un PNG en PNG et laisse une petite image à sa taille', function (): void {
    $resized = app(ResizeUploadedImage::class)->handle(UploadedFile::fake()->image('logo.png', 600, 200));

    expect($resized->extension)->toBe('png');
    expect($resized->width)->toBe(600);
    expect($resized->height)->toBe(200);
    expect(getimagesizefromstring($resized->content)[2])->toBe(IMAGETYPE_PNG);
});

it('refuse un fichier qui n\'est pas une image lisible', function (): void {
    app(ResizeUploadedImage::class)->handle(UploadedFile::fake()->createWithContent('affiche.jpg', 'ceci est du texte'));
})->throws(InvalidArgumentException::class);
