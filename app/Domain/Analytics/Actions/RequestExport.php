<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\Export;
use App\Domain\Analytics\Models\ExportStatus;
use App\Domain\Analytics\Models\ExportType;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Domain\Organization\Models\Organization;
use App\Jobs\ProcessExportJob;
use App\Models\User;
use App\Support\Segments\EventSegment;
use Illuminate\Support\Facades\Gate;

/**
 * Journalisation à la demande, pas à chaque changement de statut du job
 * (AC : « tout export est journalisé ») : ce qui importe pour le RGPD est
 * l'intention — qui a demandé quelles données — pas la progression
 * pending → processing → completed, qui serait un bruit inutile dans
 * l'audit si Export utilisait le trait Auditable générique.
 */
final class RequestExport
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  list<string>  $columns
     */
    public function handle(
        Organization $organization,
        int $eventId,
        ExportType $type,
        array $columns,
        ?EventSegment $segment,
        User $user,
    ): Export {
        Gate::forUser($user)->authorize('exportData', $organization);

        $export = Export::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'type' => $type,
            'status' => ExportStatus::Pending,
            'columns' => $columns,
            'segment' => $segment?->value,
            'requested_by' => $user->id,
        ]);

        $this->recordAuditLog->handle(
            action: 'export.requested',
            causer: $user,
            subject: $export,
            metadata: ['type' => $type->value, 'columns' => $columns, 'segment' => $segment?->value, 'event_id' => $eventId],
            organizationId: $organization->id,
        );

        ProcessExportJob::dispatch($export->id, $organization->id, $eventId);

        // Avec le driver de queue "sync" (tests, et selon la configuration
        // de prod), dispatch() exécute déjà le job de façon synchrone via sa
        // propre instance du modèle : celle-ci ne se relit jamais sans
        // refresh(), donc resterait figée à "pending" sans cet appel.
        return $export->refresh();
    }
}
