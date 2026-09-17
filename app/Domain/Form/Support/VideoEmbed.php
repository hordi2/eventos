<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * Vidéo d'un bloc « Texte, image, vidéo » : seules YouTube et Vimeo sont
 * reconnues, et seule leur adresse d'intégration sans cookie est produite.
 * Le lecteur n'est chargé qu'au clic de l'invité (décision produit) : la
 * page garde son poids et rien n'est envoyé au service avant ce clic.
 */
final class VideoEmbed
{
    private function __construct(
        public readonly string $provider,
        public readonly string $embedUrl,
    ) {}

    public static function from(mixed $url): ?self
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('~^https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $matches) === 1) {
            return new self('YouTube', "https://www.youtube-nocookie.com/embed/{$matches[1]}?autoplay=1&rel=0");
        }

        if (preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d{6,15})~', $url, $matches) === 1) {
            return new self('Vimeo', "https://player.vimeo.com/video/{$matches[1]}?autoplay=1");
        }

        return null;
    }
}
