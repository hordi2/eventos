<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Le serveur web sert public/ avant de passer la main à Laravel : une route
// dont le premier segment porte le nom d'un dossier de public/ n'est jamais
// atteinte (404 ou 403), alors que les tests, qui ne passent pas par le
// serveur, la voient fonctionner. C'est arrivé avec /images.
it('ne donne à aucune route le nom d\'un dossier de public/', function (): void {
    $collisions = collect(Route::getRoutes()->getRoutes())
        // storage/{path} : routes de Laravel pour les adresses temporaires du
        // disque local, qui visent exprès le même préfixe que public/storage.
        ->reject(fn ($route): bool => str_starts_with((string) $route->getName(), 'storage.'))
        ->map(fn ($route): string => explode('/', ltrim($route->uri(), '/'))[0])
        ->filter(fn (string $segment): bool => $segment !== '' && ! str_starts_with($segment, '{') && is_dir(public_path($segment)))
        ->unique()
        ->values()
        ->all();

    expect($collisions)->toBe([]);
});
