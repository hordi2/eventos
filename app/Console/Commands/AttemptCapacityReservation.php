<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Tente une unique réservation puis quitte, en imprimant le résultat en JSON.
 *
 * N'existe que pour piloter le test de concurrence du moteur de capacité
 * (T-024) : PHP ne dispose pas de pcntl dans cette image, donc la seule façon
 * de produire une vraie concurrence au niveau du système d'exploitation (et
 * non une simulation séquentielle) est de lancer plusieurs processus `php
 * artisan` indépendants via Process::pool() et de laisser le verrou Redis
 * arbitrer entre eux, exactement comme il le ferait entre plusieurs requêtes
 * HTTP simultanées en production.
 */
final class AttemptCapacityReservation extends Command
{
    protected $signature = 'capacity:attempt-reservation
        {organizationId : ID de l\'organisation}
        {holderType : Type du holder (ex. event)}
        {holderId : ID du holder}
        {capacityLimit : Limite de capacité}
        {reservationKey : Clé de réservation, unique par tentative}
        {--waitlist : Autoriser la liste d\'attente}
        {--lock-wait= : Délai maximal d\'attente du verrou, en secondes}';

    protected $description = 'Tente une réservation de capacité et imprime le résultat en JSON (outil de test de concurrence T-024)';

    public function handle(ReserveCapacity $reserveCapacity, CurrentOrganization $currentOrganization): int
    {
        $organizationId = (int) $this->argument('organizationId');
        $currentOrganization->set($organizationId);

        $lockWait = $this->option('lock-wait');

        $arguments = [
            'organizationId' => $organizationId,
            'holderType' => (string) $this->argument('holderType'),
            'holderId' => (string) $this->argument('holderId'),
            'capacityLimit' => (int) $this->argument('capacityLimit'),
            'reservationKey' => (string) $this->argument('reservationKey'),
            'allowWaitlist' => (bool) $this->option('waitlist'),
        ];

        if ($lockWait !== null) {
            $arguments['lockWaitSeconds'] = (int) $lockWait;
        }

        $result = $reserveCapacity->handle(...$arguments);

        $this->output->write(json_encode([
            'outcome' => $result->outcome->value,
            'waitlist_position' => $result->waitlistPosition,
        ]));

        return self::SUCCESS;
    }
}
