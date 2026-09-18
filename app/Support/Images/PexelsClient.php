<?php

declare(strict_types=1);

namespace App\Support\Images;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * API Pexels (https://www.pexels.com/api/documentation/) : recherche de
 * photos libres de droits et téléchargement de celle que l'organisateur
 * choisit. La photo est ensuite copiée dans « Mes images » : les pages
 * invité ne contactent jamais Pexels.
 *
 * Toute erreur réseau ou HTTP devient StockPhotosUnavailableException, dont
 * le message s'affiche tel quel dans le constructeur.
 */
final class PexelsClient
{
    private const BASE_URL = 'https://api.pexels.com/v1';

    private const PER_PAGE = 24;

    private const TIMEOUT_SECONDS = 10;

    /**
     * Le quota gratuit est de 200 requêtes par heure : une recherche déjà
     * faite est resservie depuis le cache plutôt que redemandée.
     */
    private const SEARCH_CACHE_SECONDS = 3600;

    /**
     * Seul hôte d'où une photo est téléchargée : l'adresse vient de la
     * réponse de l'API, jamais du navigateur, et reste vérifiée.
     */
    private const IMAGE_HOST = 'images.pexels.com';

    private const MAX_DOWNLOAD_BYTES = 15 * 1024 * 1024;

    /**
     * Sans requête, la sélection du moment de Pexels : l'onglet s'ouvre
     * ainsi sur des photos plutôt que sur un champ vide.
     *
     * @return array{photos: list<array<string, mixed>>, has_more: bool}
     */
    public function search(string $query, int $page): array
    {
        $query = trim($query);
        $path = $query === '' ? '/curated' : '/search';
        $parameters = array_filter([
            'query' => $query === '' ? null : $query,
            'locale' => $query === '' ? null : 'fr-FR',
            'per_page' => self::PER_PAGE,
            'page' => $page,
        ]);

        return Cache::remember(
            'pexels:'.sha1($path.'|'.http_build_query($parameters)),
            self::SEARCH_CACHE_SECONDS,
            function () use ($path, $parameters): array {
                $payload = $this->get($path, $parameters);
                $photos = is_array($payload['photos'] ?? null) ? $payload['photos'] : [];

                return [
                    'photos' => array_values(array_filter(array_map($this->present(...), $photos))),
                    'has_more' => is_string($payload['next_page'] ?? null),
                ];
            },
        );
    }

    /**
     * Télécharge la photo choisie dans une taille déjà proche de l'usage
     * (large2x, environ 1 900 px de large) : la bibliothèque la réduira de
     * toute façon, inutile de rapatrier l'original de plusieurs dizaines de Mo.
     *
     * @return array{content: string, photographer: string, extension: string}
     */
    public function download(int $photoId): array
    {
        $photo = $this->get("/photos/{$photoId}", []);
        $source = $photo['src']['large2x'] ?? null;

        if (! is_string($source) || parse_url($source, PHP_URL_HOST) !== self::IMAGE_HOST) {
            throw StockPhotosUnavailableException::notFound();
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS * 2)->get($source);
        } catch (ConnectionException) {
            throw StockPhotosUnavailableException::unreachable();
        }

        if (! $response->successful() || strlen($response->body()) > self::MAX_DOWNLOAD_BYTES) {
            throw StockPhotosUnavailableException::unreachable();
        }

        return [
            'content' => $response->body(),
            'photographer' => is_string($photo['photographer'] ?? null) ? $photo['photographer'] : 'Pexels',
            'extension' => str_contains((string) $response->header('Content-Type'), 'png') ? 'png' : 'jpg',
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function get(string $path, array $parameters): array
    {
        $key = config('services.pexels.key');

        if (! is_string($key) || $key === '') {
            throw StockPhotosUnavailableException::notConfigured();
        }

        try {
            $response = Http::withHeaders(['Authorization' => $key])
                ->timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::BASE_URL.$path, $parameters);
        } catch (ConnectionException) {
            throw StockPhotosUnavailableException::unreachable();
        }

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->status() === 404) {
            throw StockPhotosUnavailableException::notFound();
        }

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            throw StockPhotosUnavailableException::unreachable();
        }

        return $payload;
    }

    /**
     * @return array{id: int, thumbnail: string, alt: string, photographer: string, photographer_url: string|null, url: string|null}|null
     */
    private function present(mixed $photo): ?array
    {
        if (! is_array($photo) || ! is_int($photo['id'] ?? null) || ! is_string($photo['src']['medium'] ?? null)) {
            return null;
        }

        return [
            'id' => $photo['id'],
            'thumbnail' => $photo['src']['medium'],
            'alt' => is_string($photo['alt'] ?? null) ? $photo['alt'] : '',
            'photographer' => is_string($photo['photographer'] ?? null) ? $photo['photographer'] : 'Pexels',
            'photographer_url' => is_string($photo['photographer_url'] ?? null) ? $photo['photographer_url'] : null,
            'url' => is_string($photo['url'] ?? null) ? $photo['url'] : null,
        ];
    }
}
