<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use App\Domain\Organization\Actions\RecordAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::updated(function (Model $model): void {
            $changes = collect($model->getChanges())->except(['updated_at'])->all();

            if ($changes === []) {
                return;
            }

            app(RecordAuditLog::class)->handle(
                action: Str::snake(class_basename($model)).'.updated',
                causer: Auth::user(),
                subject: $model,
                metadata: ['changes' => $changes],
            );
        });

        static::deleted(function (Model $model): void {
            app(RecordAuditLog::class)->handle(
                action: Str::snake(class_basename($model)).'.deleted',
                causer: Auth::user(),
                subject: $model,
            );
        });
    }
}
