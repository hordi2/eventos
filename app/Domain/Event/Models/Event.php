<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by',
        'slug',
        'title',
        'subtitle',
        'description',
        'type',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'is_online',
        'online_url',
        'venue_id',
        'parent_event_id',
        'capacity',
        'registration_opens_at',
        'registration_closes_at',
        'access_mode',
        'password_hash',
        'requires_approval',
        'allow_waitlist',
        'allow_guest_edit',
        'edit_deadline',
        'currency',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'access_mode' => EventAccessMode::class,
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime',
            'registration_closes_at' => 'immutable_datetime',
            'edit_deadline' => 'immutable_datetime',
            'is_online' => 'boolean',
            'requires_approval' => 'boolean',
            'allow_waitlist' => 'boolean',
            'allow_guest_edit' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_event_id');
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function subEvents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_event_id');
    }

    public function isSubEvent(): bool
    {
        return $this->parent_event_id !== null;
    }

    /**
     * Paires de sous-événements dont les horaires se chevauchent (M1.3 du
     * CDC : « détection des conflits d'horaires entre sessions parallèles »).
     * Les dates sont comparées en UTC, donc indépendamment du fuseau propre
     * à chaque sous-événement.
     *
     * @return list<array{Event, Event}>
     */
    public function detectSubEventScheduleConflicts(): array
    {
        $subEvents = $this->subEvents()->orderBy('start_at')->get();
        $conflicts = [];

        foreach ($subEvents as $i => $first) {
            foreach ($subEvents as $j => $second) {
                if ($j <= $i) {
                    continue;
                }

                if ($first->start_at->lessThan($second->end_at) && $second->start_at->lessThan($first->end_at)) {
                    $conflicts[] = [$first, $second];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Machine à états complète (draft → published → live → ended →
     * archived) : "live" et "ended" ne sont jamais stockés, ils sont
     * dérivés de l'heure courante dans le fuseau de l'événement.
     */
    public function computedStatus(): EventLifecycleStatus
    {
        return match ($this->status) {
            EventStatus::Draft => EventLifecycleStatus::Draft,
            EventStatus::Archived => EventLifecycleStatus::Archived,
            EventStatus::Published => $this->publishedLifecycleStatus(),
        };
    }

    private function publishedLifecycleStatus(): EventLifecycleStatus
    {
        $now = CarbonImmutable::now($this->timezone);
        $start = $this->start_at->setTimezone($this->timezone);
        $end = $this->end_at->setTimezone($this->timezone);

        return match (true) {
            $now->lessThan($start) => EventLifecycleStatus::Published,
            $now->greaterThan($end) => EventLifecycleStatus::Ended,
            default => EventLifecycleStatus::Live,
        };
    }
}
