<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Event\Models\Event;
use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne seulement quand un organisateur s'écarte du réglage par défaut
 * (tout activé) pour un événement donné — voir le docblock de la migration.
 */
final class RegistrationNotificationPreference extends Model
{
    use BelongsToOrganization;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'event_id',
        'notify_created',
        'notify_updated',
        'notify_cancelled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify_created' => 'boolean',
            'notify_updated' => 'boolean',
            'notify_cancelled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
