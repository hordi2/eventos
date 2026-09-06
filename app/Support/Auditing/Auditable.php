<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use App\Domain\Organization\Actions\RecordAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait Auditable
{
    /**
     * Colonnes jamais journalisées en clair (T-075, RGPD) : le journal
     * d'audit est immuable au niveau base (déclencheur PostgreSQL sur
     * audit_logs, voir sa migration) — une valeur qui y entre ne peut plus
     * jamais en sortir. Anonymiser un Contact ne servirait à rien si sa
     * propre modification passée avait déjà consigné l'e-mail/le nom/le
     * téléphone en clair ; on retient seulement qu'un changement a eu
     * lieu, jamais sa valeur. Ne couvre que les entrées à partir de ce
     * correctif — les entrées antérieures restent, par construction,
     * hors de portée de tout correctif logiciel.
     *
     * @var list<string>
     */
    private const REDACTED_AUDIT_FIELDS = [
        'email',
        'phone_e164',
        'first_name',
        'last_name',
        'buyer_name',
        'buyer_email',
        'buyer_phone_e164',
        'custom_fields',
    ];

    public static function bootAuditable(): void
    {
        static::updated(function (Model $model): void {
            $changes = collect($model->getChanges())
                ->except(['updated_at'])
                ->map(fn (mixed $value, string $key): mixed => in_array($key, self::REDACTED_AUDIT_FIELDS, true) ? '[redacted]' : $value)
                ->all();

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
