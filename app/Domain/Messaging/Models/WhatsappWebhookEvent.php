<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use Database\Factories\WhatsappWebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'idempotence (règle 4.4 du CLAUDE.md) : ni BelongsToOrganization
 * ni RLS, même raisonnement que EmailWebhookEvent (T-043) — un webhook
 * arrive avant qu'on sache à quelle organisation il se rapporte.
 */
final class WhatsappWebhookEvent extends Model
{
    /** @use HasFactory<WhatsappWebhookEventFactory> */
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

    protected static function newFactory(): WhatsappWebhookEventFactory
    {
        return WhatsappWebhookEventFactory::new();
    }
}
