<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SpeakerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Intervenant d'un événement (D6) : sa fiche pour la page publique, et les
 * sessions où il parle.
 */
final class Speaker extends Model
{
    /** @use HasFactory<SpeakerFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'name',
        'role',
        'company',
        'bio',
        'photo_path',
        'website_url',
        'linkedin_url',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): SpeakerFactory
    {
        return SpeakerFactory::new();
    }

    /**
     * Les sessions où il intervient : des événements secondaires (T-013).
     *
     * @return BelongsToMany<Event, $this>
     */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'session_speakers', 'speaker_id', 'event_id')->withTimestamps();
    }
}
