<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EventPollOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventPollOption extends Model
{
    /** @use HasFactory<EventPollOptionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_poll_id',
        'label',
        'position',
        'vote_count',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'vote_count' => 'integer',
        ];
    }

    protected static function newFactory(): EventPollOptionFactory
    {
        return EventPollOptionFactory::new();
    }

    /**
     * @return BelongsTo<EventPoll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(EventPoll::class, 'event_poll_id');
    }
}
