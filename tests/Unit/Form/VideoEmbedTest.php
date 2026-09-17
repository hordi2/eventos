<?php

declare(strict_types=1);

use App\Domain\Form\Support\VideoEmbed;

it('reconnaît les adresses YouTube et Vimeo et n\'en garde que le lecteur sans cookie', function (string $url, string $provider, string $embedUrl): void {
    $video = VideoEmbed::from($url);

    expect($video?->provider)->toBe($provider);
    expect($video?->embedUrl)->toBe($embedUrl);
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'YouTube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0'],
    ['https://youtu.be/dQw4w9WgXcQ?t=30', 'YouTube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0'],
    ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'YouTube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0'],
    ['https://vimeo.com/123456789', 'Vimeo', 'https://player.vimeo.com/video/123456789?autoplay=1'],
]);

it('refuse tout autre lien', function (mixed $url): void {
    expect(VideoEmbed::from($url))->toBeNull();
})->with([
    ['https://exemple.test/video.mp4'],
    ['javascript:alert(1)'],
    ['https://www.youtube.com/'],
    [''],
    [null],
]);
