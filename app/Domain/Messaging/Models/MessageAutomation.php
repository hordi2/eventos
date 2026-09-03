<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use App\Support\Segments\EventSegment;
use Database\Factories\MessageAutomationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * event_id reste une colonne simple, jamais une relation Eloquent :
 * Domain/Messaging ne dépend d'aucun modèle de Domain/Event (section 3 du
 * CLAUDE.md, test d'architecture) — même règle que Registration vis-à-vis
 * d'Event.
 *
 * Exactement un des deux template_id est renseigné selon channel — jamais
 * les deux, jamais aucun (garanti par CreateMessageAutomation, pas par une
 * contrainte SQL : une contrainte CHECK inter-colonnes aurait ajouté une
 * migration supplémentaire pour un gain marginal, la seule voie d'écriture
 * est déjà cette action).
 */
final class MessageAutomation extends Model
{
    /** @use HasFactory<MessageAutomationFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'channel',
        'email_template_id',
        'whatsapp_template_id',
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
            'channel' => MessageChannel::class,
            'type' => MessageAutomationType::class,
            'segment' => EventSegment::class,
            'status' => MessageAutomationStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): MessageAutomationFactory
    {
        return MessageAutomationFactory::new();
    }

    /**
     * @return BelongsTo<EmailTemplate, $this>
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * @return BelongsTo<WhatsappTemplate, $this>
     */
    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
