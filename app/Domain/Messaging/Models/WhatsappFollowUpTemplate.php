<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le modèle WhatsApp approuvé qui sert à un message de suivi donné.
 */
final class WhatsappFollowUpTemplate extends Model
{
    use BelongsToOrganization;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'purpose',
        'whatsapp_template_id',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => FollowUpMessage::class,
        ];
    }

    /**
     * @return BelongsTo<WhatsappTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'whatsapp_template_id');
    }
}
