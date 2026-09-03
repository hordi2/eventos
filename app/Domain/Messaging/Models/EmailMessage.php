<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pas de relation Eloquent vers Contact : contact_id reste une colonne
 * simple, comme Registration vis-à-vis d'Event (section 3 du CLAUDE.md) —
 * Domain/Messaging ne dépend d'aucun autre module de Domain/.
 *
 * Pas de trait Auditable non plus, volontairement : une ligne par e-mail
 * envoyé rendrait le journal d'audit inexploitable au moindre envoi en
 * masse (même raisonnement que ContactImportRow). L'action d'audit
 * pertinente sera celle qui déclenche un envoi en masse (campagnes,
 * T-045), pas chaque message individuel.
 */
final class EmailMessage extends Model
{
    /** @use HasFactory<EmailMessageFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'contact_id',
        'to_email',
        'subject',
        'is_transactional',
        'status',
        'provider',
        'provider_message_id',
        'sent_at',
        'delivered_at',
        'opened_at',
        'first_clicked_at',
        'bounced_at',
        'bounce_type',
        'complained_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_transactional' => 'boolean',
            'status' => EmailMessageStatus::class,
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'first_clicked_at' => 'immutable_datetime',
            'bounced_at' => 'immutable_datetime',
            'complained_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EmailMessageFactory
    {
        return EmailMessageFactory::new();
    }
}
