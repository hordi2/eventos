<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\WhatsappMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pas de relation Eloquent vers Contact : contact_id reste une colonne
 * simple (section 3 du CLAUDE.md), même règle que EmailMessage. Pas de
 * trait Auditable non plus, pour la même raison qu'EmailMessage : une
 * ligne par message rendrait le journal d'audit inexploitable au moindre
 * envoi en masse.
 */
final class WhatsappMessage extends Model
{
    /** @use HasFactory<WhatsappMessageFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'contact_id',
        'whatsapp_template_id',
        'to_phone_e164',
        'status',
        'provider',
        'provider_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsappMessageStatus::class,
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): WhatsappMessageFactory
    {
        return WhatsappMessageFactory::new();
    }
}
