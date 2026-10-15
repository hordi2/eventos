<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SessionQuestionUpvoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un vote « moi aussi » d'un participant sur une question posée par un
 * autre (D9). Une ligne par (question, inscription) : la contrainte
 * unique empêche un même participant de voter deux fois.
 */
final class SessionQuestionUpvote extends Model
{
    /** @use HasFactory<SessionQuestionUpvoteFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'session_question_id',
        'registration_id',
    ];

    protected static function newFactory(): SessionQuestionUpvoteFactory
    {
        return SessionQuestionUpvoteFactory::new();
    }

    /**
     * @return BelongsTo<SessionQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SessionQuestion::class, 'session_question_id');
    }
}
