<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\AuditLog;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

final class RecordAuditLog
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        string $action,
        ?Model $causer = null,
        ?Model $subject = null,
        array $metadata = [],
        ?int $organizationId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'organization_id' => $organizationId ?? app(CurrentOrganization::class)->id(),
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
