<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Support\Antivirus\FileScanStatus;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SpeakerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Intervenant d'un événement (D6) : sa fiche pour la page publique, et les
 * sessions où il parle.
 */
final class Speaker extends Model
{
    /** @use HasFactory<SpeakerFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    public const SUPPORT_QUARANTINE_DIRECTORY = 'speaker-supports/quarantaine';

    public const SUPPORT_DIRECTORY = 'speaker-supports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'name',
        'role',
        'company',
        'email',
        'bio',
        'photo_path',
        'website_url',
        'linkedin_url',
        'position',
        'portal_token',
        'portal_sent_at',
        'confirmed_at',
        'declined_at',
        'response_note',
        'support_disk',
        'support_path',
        'support_original_name',
        'support_mime_type',
        'support_size_bytes',
        'support_scan_status',
        'support_scan_signature',
        'support_uploaded_at',
        'support_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'portal_sent_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
            'support_size_bytes' => 'integer',
            'support_scan_status' => FileScanStatus::class,
            'support_uploaded_at' => 'immutable_datetime',
            'support_scanned_at' => 'immutable_datetime',
        ];
    }

    /**
     * Lien personnel du portail : posé une fois pour toutes à la création,
     * comme celui d'un invité (EventInvitee).
     */
    protected static function booted(): void
    {
        self::creating(function (self $speaker): void {
            $speaker->portal_token ??= Str::random(40);
        });
    }

    /**
     * Réponse de l'intervenant à son créneau : « pending » tant qu'il ne
     * s'est pas prononcé.
     */
    public function slotStatus(): string
    {
        return match (true) {
            $this->confirmed_at !== null => 'confirmed',
            $this->declined_at !== null => 'declined',
            default => 'pending',
        };
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
