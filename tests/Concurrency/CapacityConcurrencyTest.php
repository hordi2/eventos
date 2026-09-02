<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\Capacity\Models\WaitlistEntryStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * T-024 : « Verrou Redis empêchant le dépassement en cas d'inscriptions
 * simultanées », prouvé par une vraie concurrence au niveau du système
 * d'exploitation plutôt que simulée. PHP n'a pas pcntl dans cette image, donc
 * chaque tentative est un processus `php artisan` indépendant, avec sa
 * propre connexion PostgreSQL et sa propre connexion Redis — exactement ce
 * qui se passerait avec des requêtes HTTP simultanées en production.
 *
 * Les 100 tentatives sont lancées par lots de 20 processus réellement
 * concurrents plutôt que les 100 d'un seul coup : chaque lot suffit à
 * exposer une condition de course s'il n'y avait pas de verrou (deux
 * processus qui démarrent en même temps et lisent le même compte), tout en
 * gardant le pic mémoire raisonnable. Lancer les 100 simultanément a fait
 * dépasser la mémoire allouée à l'environnement de développement local et
 * tué le conteneur applicatif (OOM) lors de la mise au point de ce test —
 * un risque qui existerait tout autant en CI.
 */
it('accepte exactement 50 réservations sur 100 tentatives, par lots concurrents, pour une capacité de 50', function (): void {
    $organization = Organization::factory()->create();
    $holderId = 'concurrency-'.Str::uuid();
    $capacity = 50;
    $attempts = 100;
    $batchSize = 20;

    $pgsql = config('database.connections.pgsql');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) $pgsql['host'],
        'DB_PORT' => (string) $pgsql['port'],
        'DB_DATABASE' => (string) $pgsql['database'],
        'DB_USERNAME' => (string) $pgsql['username'],
        'DB_PASSWORD' => (string) $pgsql['password'],
        // phpunit.xml force CACHE_STORE=array pour la suite de tests (rapide,
        // isolé par processus). C'est correct pour des tests à un seul
        // processus, mais rendrait ce test-ci silencieusement inutile : sans
        // ce remplacement, chaque processus enfant aurait son propre verrou
        // en mémoire, jamais partagé avec les autres — aucune contention ne
        // serait jamais observée. On force donc ici le verrou Redis réel,
        // celui utilisé en production (.env), pour prouver le comportement
        // sous charge réelle plutôt qu'une absence de vérification.
        'CACHE_STORE' => 'redis',
    ];

    try {
        $outcomes = new Collection;

        foreach (range(0, intdiv($attempts, $batchSize) - 1) as $batch) {
            $results = Process::pool(function (Pool $pool) use ($batch, $batchSize, $organization, $holderId, $capacity, $env): void {
                foreach (range(0, $batchSize - 1) as $offset) {
                    $i = $batch * $batchSize + $offset;

                    $pool->command([
                        'php', 'artisan', 'capacity:attempt-reservation',
                        (string) $organization->id, 'event', $holderId, (string) $capacity,
                        "concurrency-{$i}", '--waitlist', '--lock-wait=15',
                    ])->path(base_path())->env($env)->timeout(30);
                }
            })->start()->wait();

            $outcomes = $outcomes->merge($results->collect()->map(function ($result): string {
                expect($result->successful())->toBeTrue(
                    "exit={$result->exitCode()} out={$result->output()} err={$result->errorOutput()}"
                );

                return json_decode($result->output(), true)['outcome'];
            }));
        }

        expect($outcomes->filter(fn (string $o): bool => $o === 'accepted'))->toHaveCount($capacity);
        expect($outcomes->filter(fn (string $o): bool => $o === 'waitlisted'))->toHaveCount($attempts - $capacity);

        app(CurrentOrganization::class)->set($organization);

        $heldCount = CapacityHold::query()
            ->where('holder_type', 'event')->where('holder_id', $holderId)
            ->where('status', CapacityHoldStatus::Held)->count();
        expect($heldCount)->toBe($capacity);

        $positions = WaitlistEntry::query()
            ->where('holder_type', 'event')->where('holder_id', $holderId)
            ->where('status', WaitlistEntryStatus::Waiting)
            ->orderBy('position')->pluck('position')->all();
        expect($positions)->toBe(range(1, $attempts - $capacity));
    } finally {
        app(CurrentOrganization::class)->set($organization);
        CapacityHold::query()->where('holder_id', $holderId)->delete();
        WaitlistEntry::query()->where('holder_id', $holderId)->delete();
        app(CurrentOrganization::class)->clear();
        $organization->forceDelete();
    }
})->group('concurrency');
