<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP stricte sur les pages publiques (§7 du CLAUDE.md).
 *
 * Alpine.js évalue ses directives (x-data, x-on…) via `new Function()` :
 * sans 'unsafe-eval' en script-src, chaque page invité du projet cesserait
 * de fonctionner. C'est une limite connue de la version standard
 * d'Alpine — son alternative, @alpinejs/csp, demande d'auditer tous les
 * x-data existants et d'ajouter une dépendance npm (§9 du CLAUDE.md,
 * jamais sans demander) : signalé, pas corrigé ici.
 *
 * style-src garde 'unsafe-inline' : les styles en ligne portent la
 * personnalisation de chaque page (couleur de marque, voile de couverture,
 * tailles en clamp()) — les générer en feuille externe nonce-protégée
 * demanderait de réécrire tout le rendu des blocs pour un gain de sécurité
 * marginal face au script-src déjà fermé.
 *
 * Le reste est fermé : aucun script injecté par une faille XSS ne peut
 * s'exécuter (script-src 'self' sans 'unsafe-inline'), aucune page tierce
 * ne peut encadrer les nôtres (frame-ancestors), aucun greffon ni balise
 * <base> détournée (object-src, base-uri).
 */
final class AddGuestContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Le fichier `public/hot` est le signal que Laravel lui-même utilise
        // (directive @vite) pour savoir si les pages chargent leurs scripts
        // depuis le serveur Vite de rechargement à chaud plutôt que depuis
        // /build. Ce serveur les sert parfois depuis un hôte ([::1])
        // qu'aucune syntaxe CSP ne peut autoriser — Chrome le refuse telle
        // quelle. La CSP n'a donc de sens que loin de ce mode : elle reste
        // active en test, recette et production, où @vite sert les fichiers
        // compilés depuis notre propre origine.
        if (is_file(public_path('hot')) || $response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    private function policy(): string
    {
        $imageHosts = implode(' ', $this->imageHosts());
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval' https://www.googletagmanager.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: {$imageHosts}",
            "connect-src 'self' https://*.google-analytics.com",
            // Mot d'accueil et vidéos de la page événement (VideoEmbed) :
            // seules ces deux origines y sont jamais résolues.
            "frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Les hôtes d'images légitimes au-delà du nôtre : le disque public
     * (symlink /storage en local) et, en production, le domaine S3-compatible
     * (Cloudflare R2) quand il est configuré — jamais en dur, lu depuis la
     * configuration des disques.
     *
     * @return list<string>
     */
    private function imageHosts(): array
    {
        $hosts = [];

        foreach (['public', 's3'] as $disk) {
            $url = (string) (config("filesystems.disks.{$disk}.url") ?? '');
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }
}
