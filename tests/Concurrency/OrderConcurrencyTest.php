<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * T-051, critère d'acceptation « test de concurrence sur les 5 derniers
 * billets » : prouve que CreateOrder — pas seulement le moteur générique de
 * capacité déjà éprouvé au T-024 — ne vend jamais plus de billets que le
 * quota du palier sous des achats simultanés réels. Même dispositif que
 * tests/Concurrency/Support/Capacity/ReserveCapacityTest.php (vrais
 * processus `php artisan`, PHP n'ayant pas pcntl dans cette image).
 */
it('vend exactement 5 billets sur 10 tentatives simultanées, pour un palier limité à 5', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id]);
    $tier = PriceTier::factory()->for($ticketType)->for($organization)->limited(5)->create();

    $attempts = 10;
    // Préfixe unique par exécution : orders.reservation_key est unique
    // globalement, et ce test ne peut pas nettoyer ses lignes ensuite
    // (Order est Auditable, audit_logs est immuable — voir plus bas) ; sans
    // ce préfixe, une seconde exécution entrerait en collision avec les
    // clés laissées par la précédente.
    $runId = (string) Str::uuid();

    $pgsql = config('database.connections.pgsql');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) $pgsql['host'],
        'DB_PORT' => (string) $pgsql['port'],
        'DB_DATABASE' => (string) $pgsql['database'],
        'DB_USERNAME' => (string) $pgsql['username'],
        'DB_PASSWORD' => (string) $pgsql['password'],
        // Même remplacement que ReserveCapacityTest : le verrou doit être
        // partagé entre les processus enfants (Redis réel), jamais le
        // cache "array" en mémoire forcé par défaut pour la suite de tests.
        'CACHE_STORE' => 'redis',
    ];

    // Pas de forceDelete() de nettoyage ici, volontairement : Order est
    // Auditable, et audit_logs est protégé par un trigger qui interdit
    // toute UPDATE/DELETE, y compris la mise à NULL en cascade déclenchée
    // par la suppression de l'organisation (§7 CLAUDE.md, deuxième
    // barrière). Une organisation ayant une activité auditée n'est donc
    // jamais réellement supprimable en base — ce test en profite plutôt
    // que de le contourner, et laisse ses données dans la base de test
    // (recréée entre chaque exécution CI).
    $results = Process::pool(function (Pool $pool) use ($attempts, $organization, $event, $ticketType, $runId, $env): void {
        foreach (range(0, $attempts - 1) as $i) {
            $pool->command([
                'php', 'artisan', 'orders:attempt-purchase',
                (string) $organization->id, (string) $event->id, (string) $ticketType->id,
                "order-concurrency-{$runId}-{$i}",
            ])->path(base_path())->env($env)->timeout(30);
        }
    })->start()->wait();

    $outcomes = $results->collect()->map(function ($result): string {
        expect($result->successful())->toBeTrue(
            "exit={$result->exitCode()} out={$result->output()} err={$result->errorOutput()}"
        );

        return json_decode($result->output(), true)['outcome'];
    });

    expect($outcomes->filter(fn (string $o): bool => $o === 'accepted'))->toHaveCount(5);
    expect($outcomes->filter(fn (string $o): bool => $o === 'rejected'))->toHaveCount($attempts - 5);

    app(CurrentOrganization::class)->set($organization);
    expect(Order::query()->count())->toBe(5);
    app(CurrentOrganization::class)->clear();
})->group('concurrency');
