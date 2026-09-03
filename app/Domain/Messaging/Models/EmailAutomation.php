<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use App\Support\Segments\EventSegment;
use Database\Factories\EmailAutomationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * event_id reste une colonne simple, jamais une relation Eloquent :
 * Domain/Messaging ne dépend d'aucun modèle de Domain/Event (section 3 du
 * CLAUDE.md, test d'architecture) — même règle que Registration vis-à-vis
 * d'Event.
 */
final class EmailAutomation extends Model
{
    /** @use HasFactory<EmailAutomationFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'email_template_id',
        'created_by',
        'type',
        'segment',
        'scheduled_at',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmailAutomationType::class,
            'segment' => EventSegment::class,
            'status' => EmailAutomationStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EmailAutomationFactory
    {
        return EmailAutomationFactory::new();
    }

    /**
     * @return BelongsTo<EmailTemplate, $this>
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
