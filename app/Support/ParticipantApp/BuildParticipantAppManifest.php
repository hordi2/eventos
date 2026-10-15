<?php

declare(strict_types=1);

namespace App\Support\ParticipantApp;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Storage;

/**
 * Le manifeste de l'application participant (D9) : une page web installable
 * « en marque blanche », aux couleurs de l'organisateur, pas d'Itaza.
 *
 * Sans logo déposé par l'organisation, l'icône reste celle d'Itaza plutôt
 * que de laisser l'installation échouer : mieux vaut une icône visible que
 * pas d'application du tout.
 */
final class BuildParticipantAppManifest
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Event $event, string $startUrl): array
    {
        $organization = $event->organization;
        $color = $organization->primary_color ?? '#1b1611';

        return [
            'name' => $event->title,
            'short_name' => mb_substr($event->title, 0, 24),
            'start_url' => $startUrl,
            'scope' => $startUrl,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $color,
            'lang' => 'fr',
            'icons' => [$this->icon($organization)],
        ];
    }

    /**
     * @return array{src: string, sizes: string, type: string}
     */
    private function icon(Organization $organization): array
    {
        if ($organization->logo_path !== null) {
            return [
                'src' => Storage::disk('public')->url($organization->logo_path),
                // « any » : le logo déposé n'a pas de dimension garantie,
                // contrairement à une icône d'application dessinée pour la
                // taille exacte attendue (règle du spec Web App Manifest).
                'sizes' => 'any',
                'type' => $this->guessType($organization->logo_path),
            ];
        }

        return ['src' => '/images/logo.png', 'sizes' => 'any', 'type' => 'image/png'];
    }

    private function guessType(string $path): string
    {
        return match (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
    }
}
