<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\PushNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Une notification push composée par l'organisateur et diffusée à tous les
 * abonnés d'un événement. event_id reste une colonne simple, jamais une
 * relation Eloquent (section 3 du CLAUDE.md, test d'architecture) — même
 * règle que MessageAutomation vis-à-vis de Event.
 */
final class PushNotification extends Model
{
    /** @use HasFactory<PushNotificationFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'title',
        'body',
        'recipient_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PushNotificationFactory
    {
        return PushNotificationFactory::new();
    }
}
