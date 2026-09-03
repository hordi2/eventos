<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use Database\Factories\EmailWebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'idempotence (règle 4.4 du CLAUDE.md) : ni BelongsToOrganization
 * ni RLS, pour la même raison que AuditLog — un webhook arrive avant qu'on
 * sache à quelle organisation il se rapporte.
 */
final class EmailWebhookEvent extends Model
{
    /** @use HasFactory<EmailWebhookEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'event_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EmailWebhookEventFactory
    {
        return EmailWebhookEventFactory::new();
    }
}
