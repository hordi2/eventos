<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EventPollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un sondage en direct (D9) : une question à choix unique, ouverte puis
 * fermée par l'organisateur. event_id reste une colonne simple, jamais une
 * relation Eloquent vers Domain/Event (section 3 du CLAUDE.md).
 */
final class EventPoll extends Model
{
    /** @use HasFactory<EventPollFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'question',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventPollStatus::class,
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EventPollFactory
    {
        return EventPollFactory::new();
    }

    /**
     * @return HasMany<EventPollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(EventPollOption::class)->orderBy('position');
    }
}
