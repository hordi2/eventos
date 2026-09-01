<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\RecordAuditLog;
use App\Domain\Organization\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditLogController extends Controller
{
    public function index(): Response
    {
        $logs = AuditLog::query()
            ->where('organization_id', app(CurrentOrganization::class)->id())
            ->with(['causer'])
            ->latest('created_at')
            ->paginate(50)
            ->through(function (AuditLog $log): array {
                /** @var array{id: int, action: string, causer_name: string|null, subject_type: string|null, ip_address: string|null, created_at: string} $row */
                $row = [
                    'id' => $log->id,
                    'action' => $log->action,
                    'causer_name' => $log->causer?->name,
                    'subject_type' => $log->subject_type,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->toIso8601String(),
                ];

                return $row;
            });

        return Inertia::render('AuditLog/Index', ['logs' => $logs]);
    }

    public function export(Request $request, RecordAuditLog $recordAuditLog): StreamedResponse
    {
        $organizationId = app(CurrentOrganization::class)->id();

        $recordAuditLog->handle(action: 'audit_log.exported', causer: Auth::user());

        $filename = 'journal-audit-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($organizationId): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Action', 'Auteur', 'Sujet', 'Adresse IP']);

            AuditLog::query()
                ->where('organization_id', $organizationId)
                ->with(['causer'])
                ->orderBy('created_at')
                ->chunk(500, function (Collection $logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->created_at->toIso8601String(),
                            $log->action,
                            $log->causer?->name,
                            $log->subject_type,
                            $log->ip_address,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
