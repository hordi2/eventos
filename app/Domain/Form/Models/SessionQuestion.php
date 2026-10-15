<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SessionQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une question posée par un participant, au conférencier ou à
 * l'organisateur, pendant l'événement (D9). event_id porte soit
 * l'événement principal, soit une session précise, jamais de relation
 * Eloquent vers Domain/Event (section 3 du CLAUDE.md).
 */
final class SessionQuestion extends Model
{
    /** @use HasFactory<SessionQuestionFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'registration_id',
        'body',
        'upvote_count',
        'status',
        'answer',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'upvote_count' => 'integer',
            'status' => SessionQuestionStatus::class,
            'answered_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): SessionQuestionFactory
    {
        return SessionQuestionFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }
}
